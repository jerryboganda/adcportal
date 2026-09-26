<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\AppointmentProcedure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Services\InvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Billing: study-derived invoicing, extra line items, POS payments and voiding.
 * All money math is server-authoritative. Payment methods validate against the
 * tenant's OWN configured methods (payment_methods table) — never a fixed list.
 */
class BillingController extends BaseApiController
{
    /** Active method codes of the active tenant (the only acceptable wire values). */
    private function tenantMethodCodes(): array
    {
        return PaymentMethod::forClinic($this->tenantId())
            ->where('is_active', true)
            ->pluck('code')
            ->all();
    }
    public function index(Request $request): JsonResponse
    {
        $this->denyUnless('invoice manage');

        $invoices = Invoice::forClinic($this->tenantId())
            ->with(['items', 'payments.receivedBy', 'appointment'])
            ->orderByDesc('id')
            ->get();

        return $this->ok(['invoices' => $invoices->map(fn ($i) => ApiShape::invoice($i))->all()]);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->denyUnless('invoice manage');

        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        return $this->ok(['invoice' => ApiShape::invoice(
            $invoice->fresh(['items', 'payments.receivedBy', 'appointment'])
        )]);
    }

    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        $this->denyUnless('invoice create');

        if ($appointment->business_id !== $this->tenantId()) {
            abort(404);
        }

        $validated = $request->validate([
            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            // Tenant-scoped: as a bare `integer` this stored a foreign clinic's
            // service id on a line item, and `ApiShape::invoice` echoes it back.
            'items.*.serviceId' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('business_id', $this->tenantId())),
            ],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'initialPayment' => ['nullable', 'array'],
            'initialPayment.amount' => ['required_with:initialPayment', 'numeric', 'min:0.01'],
            'initialPayment.method' => ['required_with:initialPayment', 'string', Rule::in($this->tenantMethodCodes())],
            'initialPayment.reference' => ['nullable', 'string', 'max:255'],
            'issueNow' => ['sometimes', 'boolean'],
        ]);

        // The very first collection must already respect the invoice's own
        // total — an overpayment here would otherwise seed a NEGATIVE balance
        // on a brand-new invoice (POS payments are capped by the row-locked
        // check; creation time had no equivalent gate).
        $itemTotal = 0.0;
        foreach ($validated['items'] as $item) {
            $itemTotal += max(0, (float) $item['unitPrice'] * (int) $item['quantity'] - (float) ($item['discount'] ?? 0));
        }
        $netTotal = round($itemTotal - (float) ($validated['discountAmount'] ?? 0), 2);
        $netWithTax = round(max(0, $netTotal) * (1 + ((float) ($validated['taxRate'] ?? 0) / 100)), 2);

        if (isset($validated['initialPayment'])
            && (float) $validated['initialPayment']['amount'] > $netWithTax + 0.001) {
            abort(422, 'Initial payment exceeds the invoice total ('.number_format($netWithTax, 2).').');
        }

        $invoice = DB::transaction(function () use ($validated, $appointment) {
            $invoice = Invoice::create([
                'patient_id' => $appointment->customer_id,
                'appointment_id' => $appointment->id,
                'tax_rate' => (float) ($validated['taxRate'] ?? 0),
                'manual_discount' => (float) ($validated['discountAmount'] ?? 0),
                'notes' => $validated['notes'] ?? null,
                'business_id' => $this->tenantId(),
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'service_id' => $item['serviceId'] ?? null,
                    'description' => $item['description'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unitPrice'],
                    'discount' => (float) ($item['discount'] ?? 0),
                    'line_total' => max(0, (float) $item['unitPrice'] * (int) $item['quantity'] - (float) ($item['discount'] ?? 0)),
                ]);
            }

            $invoice->recalculateTotals();

            if (! empty($validated['issueNow']) || isset($validated['initialPayment'])) {
                $invoice->forceFill(['issued_by' => Auth::id(), 'issued_at' => now()])->save();
            }

            if (isset($validated['initialPayment']) && (float) $validated['initialPayment']['amount'] > 0) {
                $this->recordPayment($invoice, $validated['initialPayment']);
            }

            $this->audit('invoice_created', $invoice, [
                'summary' => "Invoice {$invoice->invoice_number} created for {$appointment->patientDisplayName()} (#{$appointment->token_number}).",
                'appointment_id' => $appointment->id,
            ]);

            return $invoice;
        });

        return response()->json([
            'data' => ['invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment']))],
        ], 201);
    }

    public function addItem(Request $request, Invoice $invoice): JsonResponse
    {
        $this->denyUnless('invoice edit');

        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($invoice->status === Invoice::STATUS_VOID) {
            abort(422, 'Cannot modify a voided invoice.');
        }

        $validated = $request->validate([
            'serviceId' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('business_id', $this->tenantId())),
            ],
            'description' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'unitPrice' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $invoice) {
            $invoice->items()->create([
                'service_id' => $validated['serviceId'] ?? null,
                'description' => $validated['description'],
                'quantity' => (int) $validated['quantity'],
                'unit_price' => (float) $validated['unitPrice'],
                'discount' => (float) ($validated['discount'] ?? 0),
                'line_total' => max(0, (float) $validated['unitPrice'] * (int) $validated['quantity'] - (float) ($validated['discount'] ?? 0)),
            ]);

            $invoice->recalculateTotals();
        });

        return $this->ok(['invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment']))]);
    }

    public function addPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->denyUnless('invoice payment');

        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($invoice->status === Invoice::STATUS_VOID) {
            abort(422, 'Invoice is voided.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in($this->tenantMethodCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Balance check rides INSIDE the transaction against the locked row so
        // two concurrent POS terminals cannot both pass against the same due.
        $payment = DB::transaction(function () use ($validated, $invoice) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            $due = (float) $locked->balance_due;
            if ((float) $validated['amount'] > $due + 0.001) {
                abort(422, 'Payment exceeds balance due ('.number_format($due, 2).').');
            }

            return $this->recordPayment($locked, $validated);
        });

        $this->audit('payment_recorded', $invoice, [
            'summary' => sprintf(
                'Collected Rs. %s via %s for %s%s',
                number_format((float) $validated['amount'], 2),
                strtoupper($validated['method']),
                $invoice->invoice_number,
                ($validated['reference'] ?? '') !== '' ? ' (ref: '.$validated['reference'].')' : '',
            ),
            'amount' => $validated['amount'],
            'method' => $validated['method'],
        ]);

        $this->notify([
            'title' => 'Payment Received (Rs. '.number_format((float) $validated['amount'], 2).')',
            'message' => sprintf(
                '%s settlement of Rs. %s collected via %s for invoice %s.',
                $invoice->patient?->customer?->name ?? 'Patient',
                number_format((float) $validated['amount'], 2),
                strtoupper($validated['method']),
                $invoice->invoice_number,
            ),
            'category' => 'billing',
            'priority' => 'low',
            'appointment_id' => $invoice->appointment_id,
            'token_number' => $invoice->appointment?->token_number,
            'patient_name' => $invoice->patient?->customer?->name,
            'target_tab' => 'billing',
            'action_label' => 'View Invoices',
        ]);

        return $this->ok([
            'invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment'])),
            'paymentId' => (string) $payment->id,
        ]);
    }

    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $this->denyUnless('invoice delete');

        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ((float) $invoice->paid_total > 0) {
            abort(422, 'Cannot void an invoice that has payments. Refund first.');
        }

        $reason = trim((string) $request->input('reason', ''));

        $invoice->forceFill([
            'status' => Invoice::STATUS_VOID,
            'voided_by' => Auth::id(),
            'voided_at' => now(),
            'notes' => trim(($invoice->notes ? $invoice->notes.' | ' : '').'VOIDED'.($reason ? ": {$reason}" : '')),
        ])->save();

        $this->audit('invoice_voided', $invoice, [
            'summary' => "Invoice {$invoice->invoice_number} voided".($reason ? " — {$reason}" : '').'.',
            'reason' => $reason,
        ]);

        return $this->ok(['invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment']))]);
    }

    /**
     * Refund a payment (full or partial) against one recorded collection.
     *
     * Money integrity contract:
     *   - the payment row must belong to this invoice AND this tenant;
     *   - the running refund total can never exceed the amount actually
     *     collected on that row (recomputed under a row lock so two
     *     concurrent terminals cannot double-refund);
     *   - the refund is stored as a NEGATIVE payment row carrying the id of
     *     the collection it reverses, so paid_total/status/paid history stay
     *     a single consistent ledger (sum of one column) with no new concept;
     *   - the invoice is never re-issued: refunds cannot resurrect a voided
     *     document of record.
     */
    public function refundPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->denyUnless('invoice refund');

        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        if ($invoice->status === Invoice::STATUS_VOID) {
            abort(422, 'Cannot refund against a voided invoice.');
        }

        $validated = $request->validate([
            'paymentId' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'method' => ['nullable', 'string', Rule::in($this->tenantMethodCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $refund = DB::transaction(function () use ($validated, $invoice) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            // Tenant guard on the payment row itself — a guessed id from
            // another clinic must resolve to 404, not to a refund.
            $payment = $locked->payments()->whereKey((int) $validated['paymentId'])->first();

            if (! $payment) {
                abort(404);
            }

            if ((float) $payment->amount <= 0) {
                abort(422, 'Only a collected payment can be refunded.');
            }

            $alreadyRefunded = (float) $locked->payments()
                ->where('refunds_payment_id', $payment->id)
                ->sum('amount');
            $refundable = round((float) $payment->amount + (float) $alreadyRefunded, 2); // refund rows are negative

            if ((float) $validated['amount'] > $refundable + 0.001) {
                abort(422, 'Refund exceeds the collectable amount on this payment ('.number_format($refundable, 2).').');
            }

            $payment->forceFill(['refunded_at' => $alreadyRefunded < 0 ? $payment->refunded_at : now()])->save();

            return app(InvoicePaymentService::class)->record(
                $locked,
                -1 * (float) $validated['amount'],
                $validated['method'] ?? $payment->method,
                'REFUND: '.($validated['reason'] !== '' ? $validated['reason'].' — ' : '').($validated['reference'] ?? $payment->reference ?? ''),
                Auth::id(),
                (int) $payment->id,
            );
        });

        $this->audit('payment_refunded', $invoice, [
            'summary' => sprintf(
                'Refunded Rs. %s on invoice %s (%s).',
                number_format((float) $validated['amount'], 2),
                $invoice->invoice_number,
                $validated['reason'],
            ),
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
            'refunded_payment_id' => (int) $validated['paymentId'],
        ]);

        $this->notify([
            'title' => 'Refund Issued (Rs. '.number_format((float) $validated['amount'], 2).')',
            'message' => sprintf(
                'Refund of Rs. %s processed for invoice %s — %s.',
                number_format((float) $validated['amount'], 2),
                $invoice->invoice_number,
                $validated['reason'],
            ),
            'category' => 'billing',
            'priority' => 'low',
            'appointment_id' => $invoice->appointment_id,
            'token_number' => $invoice->appointment?->token_number,
            'patient_name' => $invoice->patient?->customer?->name,
            'target_tab' => 'billing',
            'action_label' => 'View Invoices',
        ]);

        return $this->ok([
            'invoice' => ApiShape::invoice($invoice->fresh(['items', 'payments.receivedBy', 'appointment'])),
            'paymentId' => (string) $refund->id,
        ]);
    }

    /**
     * Shift reconciliation: the SYSTEM half of the cash drawer audit — the
     * exact ledger the cashier counts against. Scope = payments recorded in
     * the selected window (default: today), grouped by tender, with refund
     * rows (negative amounts) folded in. This is the number "Expected in
     * Drawer" must be built from, never all-time sums.
     */
    public function shiftSummary(Request $request): JsonResponse
    {
        $this->denyUnless('invoice manage');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $day = $validated['date'] ?? now()->toDateString();

        // One ledger arithmetic for the screen AND the printed closing
        // statement: a reconciliation that computes its own totals can certify
        // a number the register never showed.
        return $this->ok([
            'shift' => app(\App\Services\ShiftLedgerService::class)->forDay($this->tenantId(), $day),
        ]);
    }

    /**
     * Advisory Jev judgment over a counted shift: is the variance plausibly
     * explainable, and does anything in the day's money pattern warrant a
     * second look? Fail-open — null when no judgment is available, and the
     * deterministic variance arithmetic stays authoritative.
     */
    public function reviewShift(Request $request): JsonResponse
    {
        $this->denyUnless('invoice manage');

        $validated = $request->validate([
            'cashExpected' => ['required', 'numeric', 'min:0'],
            'cashCounted' => ['required', 'numeric', 'min:0'],
            'totalCollected' => ['required', 'numeric', 'min:0'],
            'refundedTotal' => ['nullable', 'numeric'],
            'paymentCount' => ['nullable', 'integer', 'min:0'],
        ]);

        $review = app(\App\Services\BillingAnomalyService::class)->reviewShift(
            (float) $validated['cashExpected'],
            (float) $validated['cashCounted'],
            (float) $validated['totalCollected'],
            (float) ($validated['refundedTotal'] ?? 0),
            (int) ($validated['paymentCount'] ?? 0),
        );

        return $this->ok(['review' => $review]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        // Printing is its own capability (revocable on its own) with the legacy
        // manage permission kept as an equivalent so existing roles are
        // unaffected.
        $this->denyUnlessAny(['invoice print', 'invoice manage'], 'invoice print');

        $pdf = app(\App\Services\Print\PrintPdfService::class);
        $document = $pdf->documentFor('invoice', $invoice, \App\Support\Print\PaperProfile::A4);
        $rendered = $pdf->render($document);

        $this->audit('document_pdf_downloaded', auth()->user(), [
            'artifact' => 'invoice',
            'document' => (string) $invoice->invoice_number,
            'paper' => 'a4',
            'driver' => $rendered['driver'],
        ]);

        return response($rendered['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace('-', '', (string) $invoice->invoice_number).'.pdf"',
            'X-Print-Driver' => $rendered['driver'],
            'X-Print-Paper' => 'a4',
        ]);
    }

    // ==================== internals ====================

    private function recordPayment(Invoice $invoice, array $payment): InvoicePayment
    {
        return app(InvoicePaymentService::class)->record(
            $invoice,
            (float) $payment['amount'],
            $payment['method'],
            $payment['reference'] ?? null,
            Auth::id(),
        );
    }
}

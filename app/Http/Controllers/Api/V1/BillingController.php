<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\Appointment;
use App\Models\AppointmentProcedure;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Billing: study-derived invoicing, extra line items, POS payments and voiding.
 * All money math is server-authoritative.
 */
class BillingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->denyUnless('invoice manage');

        $invoices = Invoice::forClinic($this->tenantId())
            ->with(['items', 'payments.receivedBy', 'appointment'])
            ->orderByDesc('id')
            ->get();

        return $this->ok(['invoices' => $invoices->map(fn ($i) => ApiShape::invoice($i))->all()]);
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
            'items.*.serviceId' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'initialPayment' => ['nullable', 'array'],
            'initialPayment.amount' => ['required_with:initialPayment', 'numeric', 'min:0.01'],
            'initialPayment.method' => ['required_with:initialPayment', 'in:cash,card,bank,mobile,insurance'],
            'initialPayment.reference' => ['nullable', 'string', 'max:255'],
            'issueNow' => ['sometimes', 'boolean'],
        ]);

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
            'serviceId' => ['nullable', 'integer'],
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
            'method' => ['required', 'in:cash,card,bank,mobile,insurance'],
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

    public function downloadPdf(Invoice $invoice)
    {
        if ($invoice->business_id !== $this->tenantId()) {
            abort(404);
        }

        $this->denyUnless('invoice manage');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'invoices.pdf',
            ['invoice' => $invoice->load(['items', 'payments', 'patient'])]
        )->setPaper('a4');

        return $pdf->download(str_replace('-', '', $invoice->invoice_number).'.pdf');
    }

    // ==================== internals ====================

    private function recordPayment(Invoice $invoice, array $payment): InvoicePayment
    {
        $model = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount' => (float) $payment['amount'],
            'method' => $payment['method'],
            'reference' => $payment['reference'] ?? null,
            'paid_at' => now(),
            'received_by' => Auth::id(),
            'business_id' => $this->tenantId(),
            'created_by' => Auth::id(),
        ]);

        $invoice->recalculateFromPayments();

        return $model;
    }
}

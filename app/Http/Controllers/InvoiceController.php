<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentProcedure;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::user()->isAbleTo('invoice manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $invoices = Invoice::forClinic()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($w) => $w->where('invoice_number', 'like', $term)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->with(['patient', 'appointment'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'billed' => (float) Invoice::forClinic()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])->sum('total'),
            'paid' => (float) Invoice::forClinic()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])->sum('paid_total'),
        ];
        $totals['due'] = round($totals['billed'] - $totals['paid'], 2);

        return view('invoices.index', compact('invoices', 'totals'));
    }

    public function show(Invoice $invoice)
    {
        if (! Auth::user()->isAbleTo('invoice manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $invoice->load(['items.serviceData', 'payments.receivedBy', 'patient', 'appointment.ServiceData']);

        return view('invoices.show', compact('invoice'));
    }

    /** Build a draft invoice from a completed study's procedure line items. */
    public function createForStudy(Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('invoice create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        if ($appointment->invoices()->exists()) {
            return redirect()->route('invoices.show', $appointment->invoices()->first())
                ->with('error', __('This study already has an invoice.'));
        }

        // Source lines: pivot rows; fall back to the primary procedure.
        $lines = $appointment->procedures()->get()->map(fn (AppointmentProcedure $p) => [
            'service_id' => $p->service_id,
            'description' => $p->description ?? optional($p->serviceData)->name ?? __('Procedure'),
            'quantity' => max(1, (int) $p->quantity),
            'unit_price' => (float) ($p->unit_price ?: optional($p->serviceData)->price ?? 0),
            'discount' => 0,
        ]);

        if ($lines->isEmpty() && $appointment->ServiceData) {
            $lines = collect([[
                'service_id' => $appointment->service_id,
                'description' => $appointment->ServiceData->name,
                'quantity' => 1,
                'unit_price' => (float) ($appointment->ServiceData->price ?? 0),
                'discount' => 0,
            ]]);
        }

        return view('invoices.create', compact('appointment', 'lines'));
    }

    public function store(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('invoice create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'issue_now' => 'sometimes|boolean',
        ]);

        $invoice = DB::transaction(function () use ($validated, $request, $appointment) {
            $invoice = Invoice::create([
                'patient_id' => $appointment->customer_id,
                'appointment_id' => $appointment->id,
                'tax_rate' => (float) ($validated['tax_rate'] ?? company_setting('invoice_tax_rate') ?: 0),
                'notes' => $validated['notes'] ?? null,
                'business_id' => getActiveBusiness(),
                'created_by' => creatorId(),
            ]);

            foreach ($validated['items'] as $item) {
                $model = new InvoiceItem([
                    'service_id' => $item['service_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount' => (float) ($item['discount'] ?? 0),
                ]);
                $invoice->items()->save($model);
            }

            $this->recalculateTotals($invoice);

            if (! empty($validated['issue_now'])) {
                $invoice->forceFill([
                    'issued_by' => Auth::id(),
                    'issued_at' => now(),
                ])->save();
            }

            AuditLog::record('invoice_created', $invoice, ['appointment_id' => $appointment->id]);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice :num created.', ['num' => $invoice->invoice_number]));
    }

    public function addPayment(Request $request, Invoice $invoice)
    {
        if (! Auth::user()->isAbleTo('invoice payment') || $invoice->status === Invoice::STATUS_VOID) {
            return redirect()->back()->with('error', __('Permission denied or invoice voided.'));
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,bank,mobile,insurance',
            'reference' => 'nullable|string|max:255',
        ]);

        $due = $invoice->balance_due;
        if ((float) $validated['amount'] > $due + 0.001) {
            return redirect()->back()->with('error', __('Payment exceeds balance due (:due).', ['due' => number_format($due, 2)]));
        }

        InvoicePayment::create([
            ...$validated,
            'invoice_id' => $invoice->id,
            'paid_at' => now(),
            'received_by' => Auth::id(),
            'business_id' => getActiveBusiness(),
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Payment recorded.'));
    }

    public function issue(Invoice $invoice)
    {
        if (! Auth::user()->isAbleTo('invoice edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        if ($invoice->issued_at === null) {
            $invoice->forceFill(['issued_by' => Auth::id(), 'issued_at' => now()])->save();
        }

        return redirect()->back()->with('success', __('Invoice issued.'));
    }

    public function void(Invoice $invoice)
    {
        if (! Auth::user()->isAbleTo('invoice delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        if ((float) $invoice->paid_total > 0) {
            return redirect()->back()->with('error', __('Cannot void an invoice that has payments. Refund first.'));
        }

        $invoice->forceFill([
            'status' => Invoice::STATUS_VOID,
            'voided_by' => Auth::id(),
            'voided_at' => now(),
        ])->save();

        AuditLog::record('invoice_voided', $invoice);

        return redirect()->back()->with('success', __('Invoice voided.'));
    }

    public function downloadPdf(Invoice $invoice)
    {
        if (! Auth::user()->isAbleTo('invoice manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', ['invoice' => $invoice->load(['items', 'payments', 'patient'])])
            ->setPaper('a4');

        $filename = str_replace('-', '', $invoice->invoice_number).'.pdf';

        return $pdf->download($filename);
    }

    private function recalculateTotals(Invoice $invoice): void
    {
        $invoice->refresh();

        $subtotal = (float) $invoice->items()->sum('line_total');
        $discount = (float) $invoice->items()->sum('discount');

        $taxable = max(0, $subtotal - $discount);
        $taxAmount = round($taxable * ((float) $invoice->tax_rate / 100), 2);

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_amount' => $taxAmount,
            'total' => round($taxable + $taxAmount, 2),
        ])->save();
    }
}

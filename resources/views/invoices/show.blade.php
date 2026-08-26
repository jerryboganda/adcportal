@extends('layouts.main')

@section('page-title')
    {{ __('Invoice') }} {{ $invoice->invoice_number }}
@endsection

@section('page-breadcrumb')
    {{ __('Invoices') }}, {{ $invoice->invoice_number }}
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __('Invoice') }} {{ $invoice->invoice_number }}</h5>
                        <small class="text-muted">{{ __('Issued') }}: {{ optional($invoice->issued_at)?->format('d M Y') ?? __('not issued yet') }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        @if(!$invoice->issued_at)
                            @permission('invoice edit')
                            <form method="POST" action="{{ route('invoices.issue', $invoice) }}">@csrf
                                <button class="btn btn-sm btn-primary">{{ __('Issue Invoice') }}</button>
                            </form>
                            @endpermission
                        @endif
                        @permission('invoice delete')
                        <form method="POST" action="{{ route('invoices.void', $invoice) }}">@csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                {{ (float)$invoice->paid_total > 0 ? 'disabled' : '' }}
                                data-adc-confirm
                                data-adc-confirm-message="{{ __('Void this invoice? This cannot be undone.') }}"
                                data-adc-confirm-text="{{ __('Void Invoice') }}">{{ __('Void') }}</button>
                        </form>
                        @endpermission
                        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-sm btn-secondary"><i class="ti ti-printer me-1"></i>{{ __('PDF / Print') }}</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3 small text-muted">
                        <div class="col-6">
                            <strong class="text-dark">{{ __('Patient') }}:</strong> {{ optional($invoice->patient)->name ?? '-' }}<br>
                            <strong class="text-dark">{{ __('Study') }}:</strong> #APP{{ str_pad((string)$invoice->appointment_id, 4, '0', STR_PAD_LEFT) }}
                            — {{ optional(optional($invoice->appointment)->ServiceData)->name }}
                        </div>
                    </div>
                    <table class="table align-middle">
                        <thead><tr><th>{{ __('Description') }}</th><th class="text-center">{{ __('Qty') }}</th>
                            <th class="text-end">{{ __('Unit Price') }}</th><th class="text-end">{{ __('Discount') }}</th>
                            <th class="text-end">{{ __('Line Total') }}</th></tr></thead>
                        <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">{{ currency_format_with_sym($item->unit_price) }}</td>
                                <td class="text-end">{{ currency_format_with_sym($item->discount) }}</td>
                                <td class="text-end fw-bold">{{ currency_format_with_sym($item->line_total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="border-top">
                        <tr><td colspan="4" class="text-end text-muted">{{ __('Subtotal') }}</td><td class="text-end">{{ currency_format_with_sym($invoice->subtotal) }}</td></tr>
                        <tr><td colspan="4" class="text-end text-muted">{{ __('Tax') }} ({{ $invoice->tax_rate }}%)</td><td class="text-end">{{ currency_format_with_sym($invoice->tax_amount) }}</td></tr>
                        <tr><td colspan="4" class="text-end fw-bold">{{ __('Total') }}</td><td class="text-end fs-5 fw-bold">{{ currency_format_with_sym($invoice->total) }}</td></tr>
                        <tr><td colspan="4" class="text-end text-success">{{ __('Paid') }}</td><td class="text-end text-success">{{ currency_format_with_sym($invoice->paid_total) }}</td></tr>
                        <tr><td colspan="4" class="text-end text-danger fw-bold">{{ __('Balance Due') }}</td><td class="text-end text-danger fw-bold">{{ currency_format_with_sym($invoice->balance_due) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Record Payment') }}</h6></div>
                <div class="card-body">
                    @if($invoice->status !== \App\Models\Invoice::STATUS_VOID)
                        @permission('invoice payment')
                        <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="row g-2">
                            @csrf
                            @if ($errors->any())
                                <div class="col-12">
                                    <div class="alert alert-danger py-2 mb-0 small" role="alert">{{ $errors->first() }}</div>
                                </div>
                            @endif
                            <div class="col-12">
                                <label class="form-label small">{{ __('Amount') }}</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" name="amount"
                                       class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', number_format($invoice->balance_due, 2, '.', '')) }}" required>
                                @error('amount')
                                    <span class="invalid-feedback d-block" role="alert"><small>{{ $message }}</small></span>
                                @enderror
                            </div>
                            <div class="col-7">
                                <label class="form-label small">{{ __('Method') }}</label>
                                <select name="method" class="form-select">
                                    @foreach(['cash' => __('Cash'), 'card' => __('Card'), 'bank' => __('Bank Transfer'), 'mobile' => __('Mobile Wallet'), 'insurance' => __('Insurance')] as $m => $mLabel)
                                        <option value="{{ $m }}" {{ old('method', 'cash') === $m ? 'selected' : '' }}>{{ $mLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label small">{{ __('Reference') }}</label>
                                <input name="reference" class="form-control" value="{{ old('reference') }}">
                            </div>
                            <div class="col-12"><button class="btn btn-success w-100 btn-sm">{{ __('Save Payment') }}</button></div>
                        </form>
                        @endpermission
                    @else
                        <p class="text-muted mb-0">{{ __('This invoice is voided.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Payment History') }}</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse($invoice->payments as $pay)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong>{{ currency_format_with_sym($pay->amount) }}</strong> · {{ ucfirst($pay->method) }}
                                @if($pay->reference)<br><small class="text-muted">{{ $pay->reference }}</small>@endif
                            </span>
                            <small class="text-muted text-end">{{ optional($pay->paid_at)->format('d M H:i') }}<br>{{ optional($pay->receivedBy)->name }}</small>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('No payments recorded.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

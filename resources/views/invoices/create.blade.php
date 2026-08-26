@extends('layouts.main')

@section('page-title')
    {{ __('New Invoice') }} — Study #{{ $appointment->id }}
@endsection

@section('page-breadcrumb')
    {{ __('Invoices') }}, {{ __('Create from Study') }}
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5>{{ __('Invoice for') }} {{ $appointment->patientDisplayName() }}
                <small class="text-muted">({{ optional($appointment->ServiceData)->name }} — {{ $appointment->date }})</small></h5>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('invoices.store-from-study', $appointment->id) }}" id="invoice-form">
                @csrf
                <input type="hidden" name="issue_now" value="1">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <table class="table align-middle" id="line-items">
                            <thead><tr>
                                <th style="min-width:260px">{{ __('Description') }}</th>
                                <th style="width:90px">{{ __('Qty') }}</th>
                                <th style="width:140px">{{ __('Unit Price') }}</th>
                                <th style="width:130px">{{ __('Discount') }}</th>
                                <th style="width:110px">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">
                                        <i class="ti ti-plus"></i> {{ __('Line') }}
                                    </button>
                                </th>
                            </tr></thead>
                            <tbody>
                            @foreach($lines as $i => $line)
                                <tr>
                                    <td><input name="items[{{ $i }}][description]" value="{{ old('items.'.$i.'.description', $line['description']) }}" class="form-control form-control-sm" required aria-label="{{ __('Description') }}"></td>
                                    <td><input type="number" name="items[{{ $i }}][quantity]" value="{{ old('items.'.$i.'.quantity', $line['quantity']) }}" min="1" class="form-control form-control-sm line-qty" required aria-label="{{ __('Qty') }}"></td>
                                    <td><input type="number" step="0.01" name="items[{{ $i }}][unit_price]" value="{{ old('items.'.$i.'.unit_price', number_format((float)$line['unit_price'], 2, '.', '')) }}" min="0" class="form-control form-control-sm line-price" required aria-label="{{ __('Unit Price') }}"></td>
                                    <td><input type="number" step="0.01" name="items[{{ $i }}][discount]" value="{{ old('items.'.$i.'.discount', 0) }}" min="0" class="form-control form-control-sm line-discount" aria-label="{{ __('Discount') }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" aria-label="{{ __('Remove line') }}"><i class="ti ti-trash"></i></button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Tax Rate %') }}</label>
                                <input type="number" step="0.01" id="tax-rate" value="{{ old('tax_rate', company_setting('invoice_tax_rate') ?: 0) }}" min="0" max="100" class="form-control" aria-label="{{ __('Tax Rate %') }}">
                                <input type="hidden" name="tax_rate" id="tax-rate-field" value="{{ old('tax_rate', company_setting('invoice_tax_rate') ?: 0) }}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Notes') }}</label>
                                <input name="notes" value="{{ old('notes') }}" class="form-control" placeholder="{{ __('Optional invoice note') }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="border rounded p-3" style="position:sticky; top:80px">
                            <h6 class="mb-3">{{ __('Summary') }}</h6>
                            <dl class="mb-0">
                                <dt class="d-flex justify-content-between small fw-normal"><span>{{ __('Subtotal') }}</span><dd id="sum-subtotal" class="mb-0">—</dd></dt>
                                <dt class="d-flex justify-content-between small fw-normal"><span>{{ __('Discounts') }}</span><dd id="sum-discount" class="mb-0 text-danger">—</dd></dt>
                                <dt class="d-flex justify-content-between small fw-normal"><span>{{ __('Tax') }}</span><dd id="sum-tax" class="mb-0">—</dd></dt>
                                <hr class="my-2">
                                <dt class="d-flex justify-content-between"><span>{{ __('Total') }}</span><dd id="sum-total" class="mb-0 fs-5 fw-bold">—</dd></dt>
                            </dl>
                            <button class="btn btn-primary w-100 mt-3">{{ __('Create Invoice') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
let rowIdx = {{ count($lines) }};

function recalc() {
    let subtotal = 0, discount = 0;
    document.querySelectorAll('#line-items tbody tr').forEach(function (tr) {
        const qty = parseFloat(tr.querySelector('.line-qty')?.value) || 0;
        const price = parseFloat(tr.querySelector('.line-price')?.value) || 0;
        const disc = parseFloat(tr.querySelector('.line-discount')?.value) || 0;
        subtotal += qty * price;
        discount += disc;
    });
    const taxRate = parseFloat(document.getElementById('tax-rate').value) || 0;
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxable * taxRate / 100;
    document.getElementById('sum-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('sum-discount').textContent = '-' + discount.toFixed(2);
    document.getElementById('sum-tax').textContent = tax.toFixed(2);
    document.getElementById('sum-total').textContent = (taxable + tax).toFixed(2);
}

function addRow() {
    const tbody = document.querySelector('#line-items tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input name="items[\${rowIdx}][description]" class="form-control form-control-sm" placeholder="{{ __('e.g. Contrast injection') }}" required aria-label="{{ __('Description') }}"></td>
        <td><input type="number" name="items[\${rowIdx}][quantity]" value="1" min="1" class="form-control form-control-sm line-qty" required aria-label="{{ __('Qty') }}"></td>
        <td><input type="number" step="0.01" name="items[\${rowIdx}][unit_price]" value="0" min="0" class="form-control form-control-sm line-price" required aria-label="{{ __('Unit Price') }}"></td>
        <td><input type="number" step="0.01" name="items[\${rowIdx}][discount]" value="0" min="0" class="form-control form-control-sm line-discount" aria-label="{{ __('Discount') }}"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" aria-label="{{ __('Remove line') }}"><i class="ti ti-trash"></i></button></td>`;
    tbody.appendChild(tr);
    rowIdx++;
    recalc();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalc();
}

document.addEventListener('input', function (e) {
    if (e.target.closest('#invoice-form')) recalc();
});
recalc();
</script>
@endpush

@extends('layouts.main')

@section('page-title')
    {{ __('Invoices') }}
@endsection

@section('page-breadcrumb')
    {{ __('Billing') }}, {{ __('Invoices') }}
@endsection

@section('content')
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card"><div class="card-body py-3">
                <small class="text-muted">{{ __('Total Billed') }}</small>
                <h4 class="mb-0">{{ currency_format_with_sym($totals['billed']) }}</h4>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body py-3">
                <small class="text-muted">{{ __('Collected') }}</small>
                <h4 class="mb-0 text-success">{{ currency_format_with_sym($totals['paid']) }}</h4>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body py-3">
                <small class="text-muted">{{ __('Outstanding') }}</small>
                <h4 class="mb-0 text-danger">{{ currency_format_with_sym($totals['due']) }}</h4>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">{{ __('Invoice Register') }}</h5>
            <form method="GET" class="d-flex gap-2">
                <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="{{ __('Number or patient') }}">
                <select name="status" class="form-select form-select-sm" style="max-width:140px">
                    <option value="">{{ __('Any status') }}</option>
                    @foreach(['draft','issued','partial','paid','void'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">{{ __('Search') }}</button>
            </form>
        </div>
        <div class="card-body booking-data-table">
            <table class="table table-hover align-middle">
                <thead><tr>
                    <th>{{ __('Invoice #') }}</th><th>{{ __('Patient') }}</th><th>{{ __('Study') }}</th>
                    <th>{{ __('Total') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Balance') }}</th>
                    <th>{{ __('Status') }}</th><th class="text-end">{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td class="fw-bold">{{ $inv->invoice_number }}</td>
                        <td>{{ optional($inv->patient)->name ?? $inv->appointment?->patientDisplayName() ?? '-' }}</td>
                        <td>#APP{{ str_pad((string)$inv->appointment_id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ currency_format_with_sym($inv->total) }}</td>
                        <td>{{ currency_format_with_sym($inv->paid_total) }}</td>
                        <td class="{{ $inv->balance_due > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ currency_format_with_sym($inv->balance_due) }}</td>
                        <td>
                            @php $colors = ['draft'=>'bg-secondary','issued'=>'bg-info','partial'=>'bg-warning text-dark','paid'=>'bg-success','void'=>'bg-dark']; @endphp
                            <span class="badge {{ $colors[$inv->status] }}">{{ ucfirst($inv->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('View invoice') }}" data-bs-toggle="tooltip" data-bs-original-title="{{ __('View') }}"><i class="ti ti-eye" aria-hidden="true"></i></a>
                            <a href="{{ route('invoices.pdf', $inv) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Download PDF') }}" data-bs-toggle="tooltip" data-bs-original-title="{{ __('PDF') }}"><i class="ti ti-file-download" aria-hidden="true"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-5">
                        <x-ui.empty-state icon="receipt-2" :title="__('No invoices yet.')"
                            :description="__('Invoices are created from completed imaging studies.')">
                            <x-slot:actions>
                                <a href="{{ route('reports.worklist') }}" class="btn btn-sm btn-primary">
                                    <i class="ti ti-list-check me-1"></i>{{ __('Open Reading Worklist') }}
                                </a>
                            </x-slot:actions>
                        </x-ui.empty-state>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="d-flex justify-content-center mt-2">{{ $invoices->links() }}</div>
        </div>
    </div>
@endsection

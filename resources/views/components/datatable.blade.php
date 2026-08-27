@props(['dataTable'])

@push('css')
    @include('layouts.includes.datatable-css')
@endpush

<div class="card booking-card">
    <div class="card-body table-border-style">
        <div class="booking-data-table">
            {{ $dataTable->table(['width' => '100%', 'class' => 'table align-middle']) }}
        </div>
    </div>
</div>

@push('scripts')
    @include('layouts.includes.datatable-js')
    {{ $dataTable->scripts() }}
@endpush

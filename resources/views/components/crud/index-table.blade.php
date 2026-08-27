@props(['title', 'createRoute' => null, 'createLabel' => null, 'filter' => null])
{{-- Generic CRUD index table — replaces 31× index.blade.php duplication --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $title }}</h5>
        <div class="d-flex gap-2">
            {{ $filter ?? '' }}
            @if($createRoute)
                <a href="{{ $createRoute }}" class="btn btn-sm btn-primary" data-ajax-popup="true" data-title="{{ $createLabel }}">
                    <i class="ti ti-plus" aria-hidden="true"></i> {{ $createLabel ?? __('Create') }}
                </a>
            @endif
            {{ $action ?? '' }}
        </div>
    </div>
    <div class="card-body">
        <div class="booking-data-table">
            {{ $slot }}
        </div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('assets/js/plugins/simple-datatables.js') }}"></script>
<script>
    // Unified datatable init — replaces inline 195-line card grid CSS in appointment/index
    document.addEventListener('DOMContentLoaded', function () {
        const tables = document.querySelectorAll('table[data-datatable]');
        tables.forEach(t => {
            if (t.datatable) return;
            new simpleDatatables.DataTable(t, { perPage: 15, perPageSelect: [10,15,25,50] });
        });
    });
</script>
@endpush

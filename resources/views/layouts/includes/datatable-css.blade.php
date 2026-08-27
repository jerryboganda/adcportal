<link rel="stylesheet" href="{{ asset('vendor/datatables/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/buttons.bootstrap.min.css') }}">

<style>
/* ADC datatable wrapper — horizontal scroll on narrow viewports, full table on
   wide. All visual rules come from resources/css/overrides.css (token-driven). */
.booking-data-table > .table-responsive { overflow-x: auto; }
.booking-data-table .dataTable-top,
.booking-data-table .dataTable-bottom { margin: .75rem 0; }
.booking-data-table .dataTable-selector { padding-right: 2rem; }
.booking-data-table .dataTable-pagination .active a { font-weight: 600; }
</style>

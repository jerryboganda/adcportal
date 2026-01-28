<link rel="stylesheet" href="{{ asset('vendor/datatables/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/buttons.bootstrap.min.css') }}">

<style>
/* Responsive Table - Card Layout */
@media screen and (max-width: 1400px) {
    .booking-data-table table { border: 0; }
    .booking-data-table table thead { display: none; }
    .booking-data-table table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 0.75rem;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .booking-data-table table tbody tr td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #f5f5f5;
        text-align: right;
    }
    .booking-data-table table tbody tr td:last-child { border-bottom: none; }
    .booking-data-table table tbody tr td::before {
        content: attr(data-label);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        color: #666;
        text-align: left;
        flex-shrink: 0;
        margin-right: 1rem;
    }
    .booking-data-table .table-responsive { overflow-x: visible !important; }
}
</style>

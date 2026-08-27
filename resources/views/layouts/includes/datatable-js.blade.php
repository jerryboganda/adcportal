<script src="{{ asset('vendor/datatables/datatables.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

<script>
(function () {
    "use strict";
    // Mirror thead text into data-label on every td so that any responsive-card
    // CSS (legacy or new) has the column name available. Re-runs on every draw
    // and on the initial load.
    function applyDataLabels(root) {
        var tables = (root || document).querySelectorAll('.booking-data-table table');
        tables.forEach(function (table) {
            var headers = [];
            table.querySelectorAll('thead th').forEach(function (th) {
                headers.push((th.textContent || '').trim());
            });
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                tr.querySelectorAll('td').forEach(function (td, idx) {
                    if (headers[idx]) {
                        td.setAttribute('data-label', headers[idx]);
                    }
                });
            });
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        // Initial pass after Yajra has injected rows
        setTimeout(applyDataLabels, 200);
        // Re-apply on every DataTable draw (search/sort/page)
        $(document).on('draw.dt', function () { applyDataLabels(); });
    });
})();
</script>

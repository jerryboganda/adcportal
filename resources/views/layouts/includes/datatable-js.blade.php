<script src="{{ asset('vendor/datatables/datatables.min.js')}}"></script>
<script src="{{ asset('vendor/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.colVis.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

<script>
// Add data-label attributes for responsive table
document.addEventListener(DOMContentLoaded, function() {
    setTimeout(function() {
        var tables = document.querySelectorAll(.booking-data-table table);
        tables.forEach(function(table) {
            var headers = [];
            table.querySelectorAll(thead th).forEach(function(th) {
                headers.push(th.textContent.trim());
            });
            table.querySelectorAll(tbody tr).forEach(function(tr) {
                tr.querySelectorAll(td).forEach(function(td, index) {
                    if (headers[index]) {
                        td.setAttribute(data-label, headers[index]);
                    }
                });
            });
        });
    }, 500);
});
// Re-apply on DataTable draw
.on(draw.dt, function() {
    var tables = document.querySelectorAll(.booking-data-table table);
    tables.forEach(function(table) {
        var headers = [];
        table.querySelectorAll(thead th).forEach(function(th) {
            headers.push(th.textContent.trim());
        });
        table.querySelectorAll(tbody tr).forEach(function(tr) {
            tr.querySelectorAll(td).forEach(function(td, index) {
                if (headers[index]) {
                    td.setAttribute(data-label, headers[index]);
                }
            });
        });
    });
});
</script>

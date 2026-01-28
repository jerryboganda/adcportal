<?php

namespace App\DataTables;

use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AppointmentDataTable extends DataTable
{
    protected $business;
    protected $companySetting;
    protected $moduleCache = [];

    public function getBusinessAndSettings($business, $company_settings)
    {
        $this->business = $business;
        $this->companySetting = $company_settings;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $rowColumn = ['id', 'customer_id', 'staff_id', 'service_id', 'location_id', 'referrer_id', 'appointment_status', 'date_duration'];

        $dataTable = (new EloquentDataTable($query))->addIndexColumn()
            ->addColumn('id', function (Appointment $Appointment) {
                if (request()->has('action') && request('action') === 'export') {
                    return Appointment::appointmentNumberWithFormat($Appointment->id, $this->companySetting);
                }
                return '<a href="javascript:void(0)" class="btn btn-primary" data-url="' . route('appointment.show', $Appointment->id) . '" data-size="lg" class="dropdown-item" data-ajax-popup="true" data-title="' . __('Appointment Details') . '" data-bs-toggle="tooltip" data-bs-original-title="' . __('Appointment Details') . '"><span class="text-white">' . Appointment::appointmentNumberWithFormat($Appointment->id, $this->companySetting) . '</span></a>';
            })

            ->addColumn('date_duration', function ($appointment) {
                $date = $appointment->date ?? '';
                $time = $appointment->time ?? '';
                if (request()->has('action') && request('action') === 'export') {
                    return $date . ' ' . $time;
                }
                return sprintf(
                    '<div>
                        <span>%s</span><br>
                        <span style="color: #888; font-size: 0.9em;">%s</span>
                    </div>',
                    htmlspecialchars($date),
                    htmlspecialchars($time)
                );
            })
            ->editColumn('customer_id', function (Appointment $appointment) {
                if (isset($appointment->CustomerData) && !is_null($appointment->CustomerData)) {
                    return '<span class="white-space">' . $appointment->CustomerData->name . '</span>';
                }
                return '<span class="white-space">' . $appointment->name ?? 'Guest' . '</span>';
            })
            ->filterColumn('customer_id', function ($query, $keyword) {
                $query->whereHas('CustomerData', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                })
                ->orWhere('name', 'like', "%$keyword%");
            })

            ->editColumn('staff_id', function (Appointment $apointment) {
                return '<span class="white-space">' . (!empty(optional($apointment->StaffData)) ? optional($apointment->StaffData)->name : "-") . '</span>';
            })->filterColumn('staff_id', function ($query, $keyword) {
                $query->whereHas('StaffData', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                });
            })
            ->editColumn('service_id', function (Appointment $apointment) {
                return '<span class="white-space">' . (!empty(optional($apointment->ServiceData)) ? optional($apointment->ServiceData)->name : "-") . '</span>';
            })->filterColumn('service_id', function ($query, $keyword) {
                $query->whereHas('ServiceData', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                });
            })
            ->editColumn('location_id', function (Appointment $apointment) {
                return '<span class="white-space">' . (!empty(optional($apointment->LocationData)) ? optional($apointment->LocationData)->name : "-") . '</span>';
            })->filterColumn('location_id', function ($query, $keyword) {
                $query->whereHas('LocationData', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                });
            })

            ->editColumn('referrer_id', function (Appointment $apointment) {
                return '<span class="white-space">' . (!empty(optional($apointment->ReferrerData)) ? optional($apointment->ReferrerData)->name : "-") . '</span>';
            })->filterColumn('referrer_id', function ($query, $keyword) {
                $query->whereHas('ReferrerData', function ($q) use ($keyword) {
                    $q->where('name', 'like', "%$keyword%");
                });
            })

            ->editColumn('appointment_status', function (Appointment $Appointment) {
                $title = (!empty($Appointment->StatusData) ? $Appointment->StatusData->title : (module_is_active('WaitingList') && $Appointment->appointment_status == 'Waiting List' ? $Appointment->appointment_status : 'Pending'));
                $color = (!empty($Appointment->StatusData->status_color) ? $Appointment->StatusData->status_color : '5bc0de');
                return '<a href="#" class="btn btn-sm d-inline align-items-center"data-url="' . route('appointment.status.change', $Appointment->id) . '"data-ajax-popup="true"data-size="md"data-title="' . __('Update Status') . '"data-bs-toggle="tooltip"data-bs-original-title="' . __('Update Status') . '">
                <span class="white-space" style="background-color: #' . $color . '">' . $title . '</span></a>';
            })->filterColumn('appointment_status', function ($query, $keyword) {
                $query->whereHas('StatusData', function ($q) use ($keyword) {
                    $q->where('title', 'like', "%$keyword%");
                });
            });

        // AppointmentReview
        if (module_is_active('AppointmentReview')) {
            $rowColumn = array_merge($rowColumn, ['rating']);
            $dataTable->addColumn('rating', function (Appointment $Appointment) {
                return view('appointment-review::appointment_rating.rating', compact('Appointment'));
            });
        }

        if (\Laratrust::hasPermission(['additional quanitty edit', 'appointment edit', 'appointment delete',])) {
            $rowColumn = array_merge($rowColumn, ['action']);
            $dataTable->addColumn('action', function (Appointment $Appointment) {
                $company_settings = getCompanyAllSetting();
                return view('appointment.action', compact('Appointment', 'company_settings'));
            });
        };
        return $dataTable->rawColumns($rowColumn);
    }

    /**
     * Get the query source of dataTable.
     */

    public function query(Appointment $model, Request $request): QueryBuilder
    {
        // $business       = Business::find(($request->business) ?  $request->business : getActiveBusiness());
        $Appointments = $model->with('CustomerData', 'ServiceData', 'StaffData', 'LocationData', 'StatusData', 'ReferrerData')->where('created_by', $this->business->created_by)->where('business_id', $this->business->id);

        if ($request->date) {
            $date = date('d-m-Y', strtotime($request->date));
            $Appointments = $Appointments->where('date', $date);
        }

        if ($request->service) {
            $Appointments = $Appointments->where('service_id', $request->service);
        }

        return $Appointments;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $dataTable = $this->builder()
            ->setTableId('appointment-table')
            ->columns($this->getColumns())
            ->ajax([
                'data' => 'function(d) {
                    d.date = $("input[name=date]").val();
                    d.service = $("select[name=service]").val();
                }',
            ])
            ->orderBy(0)
            ->language([
                "paginate" => [
                    "next" => '<i class="ti ti-chevron-right"></i>',
                    "previous" => '<i class="ti ti-chevron-left"></i>'
                ],
                'lengthMenu' => "_MENU_" . __('Entries Per Page'),
                "searchPlaceholder" => __('Search...'),
                "search" => "",
                "info" => __('Showing _START_ to _END_ of _TOTAL_ entries')
            ])
            ->initComplete('function() {
                var table = this;
                $("body").on("click", "#applyfilter", function() {
                    if (!$("input[name=date]").val() && !$("select[name=service]").val()) {
                        toastrs("Error!", "Please select Atleast One Filter ", "error");
                        return;
                    }
                    $("#appointment-table").DataTable().draw();
                });

                $("body").on("click", "#clearfilter", function() {
                    $("input[name=date]").val("")
                    $("select[name=service]").val("")
                    $("#appointment-table").DataTable().draw();
                });

                var searchInput = $(\'#\'+table.api().table().container().id+\' label input[type="search"]\');
                searchInput.removeClass(\'form-control form-control-sm\');
                searchInput.addClass(\'dataTable-input\');
                var select = $(table.api().table().container()).find(".dataTables_length select").removeClass(\'custom-select custom-select-sm form-control form-control-sm\').addClass(\'dataTable-selector\');
            }');

        $exportButtonConfig = [
            'extend' => 'collection',
            'className' => 'btn btn-light-secondary dropdown-toggle',
            'text' => '<i class="ti ti-download me-2" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Export"></i>',
            'buttons' => [
                [
                    'extend' => 'print',
                    'text' => '<i class="fas fa-print me-2"></i> ' . __('Print'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'csv',
                    'text' => '<i class="fas fa-file-csv me-2"></i> ' . __('CSV'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
                [
                    'extend' => 'excel',
                    'text' => '<i class="fas fa-file-excel me-2"></i> ' . __('Excel'),
                    'className' => 'btn btn-light text-primary dropdown-item',
                    'exportOptions' => ['columns' => [0, 1, 3]],
                ],
            ],
        ];

        $buttonsConfig = array_merge([
            $exportButtonConfig,
            [
                'extend' => 'reset',
                'className' => 'btn btn-light-danger',
            ],
            [
                'extend' => 'reload',
                'className' => 'btn btn-light-warning',
            ],
        ]);

        $dataTable->parameters([
            "dom" =>  "
        <'dataTable-top'<'dataTable-dropdown page-dropdown'l><'dataTable-botton table-btn dataTable-search tb-search  d-flex gap-2'Bf>>
        <'dataTable-container'<'col-sm-12'tr>>
        <'dataTable-bottom row'<'col-5'i><'col-7'p>>",
            'buttons' => $buttonsConfig,
            "drawCallback" => 'function( settings ) {
                var tooltipTriggerList = [].slice.call(
                    document.querySelectorAll("[data-bs-toggle=tooltip]")
                  );
                  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                  });
                  var popoverTriggerList = [].slice.call(
                    document.querySelectorAll("[data-bs-toggle=popover]")
                  );
                  var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                    return new bootstrap.Popover(popoverTriggerEl);
                  });
                  var toastElList = [].slice.call(document.querySelectorAll(".toast"));
                  var toastList = toastElList.map(function (toastEl) {
                    return new bootstrap.Toast(toastEl);
                  });
            }'
        ]);

        $dataTable->language([
            'buttons' => [
                'create' => __('Create'),
                'export' => __('Export'),
                'print' => __('Print'),
                'reset' => __('Reset'),
                'reload' => __('Reload'),
                'excel' => __('Excel'),
                'csv' => __('CSV'),
            ]
        ]);

        return $dataTable;
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        $columns =  [
            Column::make('id')->title(__('No'))->orderable(false),
            Column::make('date_duration')->title(__('Date/Duration')),
            Column::make('customer_id')->title(__('Customer')),
            Column::make('staff_id')->title(__('Staff')),
            Column::make('service_id')->title(__('Service')),
            Column::make('location_id')->title(__('Location')),
            Column::make('referrer_id')->title(__('Referred By')),
            Column::make('payment_type')->title(__('Payment')),
            Column::make('appointment_status')->title(__('Status'))->addClass('status_badge'),
        ];
        if (module_is_active('AppointmentReview')) {
            $ratingColumn = Column::computed('rating')
                ->title(__('Rating'))
                ->exportable(false)
                ->printable(false)
                ->searchable(false);

            array_splice($columns, 10, 0, [$ratingColumn]);
        }
        if (\Laratrust::hasPermission(['user edit', 'user delete'])) {
            $columns[] = Column::computed('action')->exportable(false)->printable(false)->width(60);
        }
        return $columns;
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Appointment_' . date('YmdHis');
    }
}

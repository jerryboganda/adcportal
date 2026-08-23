@extends('layouts.main')

@section('page-title')
    {{ __('Appointments') }}
@endsection
@section('page-breadcrumb')
    {{ __('Appointments') }}
@endsection
@php
    $company_settings = getCompanyAllSetting();
    $deposit_module_active = false;
@endphp
@section('page-action')
    <div class="d-flex col-auto gap-2">
        @stack('addButtonHook')
        @if (false)
            @permission('appointment export')
            @include('import-export::export.button', ['module' => 'appointment'])
            @endpermission
        @endif
        @permission('appointment create')
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
            data-title="{{ __('Create New Appointment') }}" data-url="{{ route('appointment.create') }}"
            data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}"><i class="ti ti-plus"></i>
        </a>
        @endpermission
    </div>
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datepicker.min.css') }}">
    <style>
        /* Professional Enterprise Card Layout - 2 Column Grid */
        #appointment-table {
            border-collapse: separate;
            border-spacing: 0;
        }

        #appointment-table thead {
            display: none;
        }

        /* Each row becomes a professional card */
        #appointment-table tbody tr {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0;
            background: linear-gradient(135deg, var(--bs-card-bg, #fff) 0%, var(--bs-light, #f8f9fa) 100%);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            padding: 0;
            margin-bottom: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.25s ease;
        }

        #appointment-table tbody tr:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--bs-primary, #6366f1);
        }

        /* Each cell is a grid item */
        #appointment-table tbody td {
            display: flex;
            flex-direction: column;
            padding: 0.75rem 1rem;
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            border-right: 1px solid rgba(0, 0, 0, 0.04);
            background: transparent;
            min-height: 60px;
            justify-content: center;
        }

        /* Remove right border on right column cells */
        #appointment-table tbody td:nth-child(2n) {
            border-right: none;
        }

        /* First row of each card - special styling for ID and Date */
        #appointment-table tbody td:nth-child(1),
        #appointment-table tbody td:nth-child(2) {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(99, 102, 241, 0.02) 100%);
        }

        /* Last row cells - no bottom border */
        #appointment-table tbody td:nth-last-child(1),
        #appointment-table tbody td:nth-last-child(2) {
            border-bottom: none;
        }

        /* Data labels */
        #appointment-table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--bs-secondary, #6c757d);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0.25rem;
            opacity: 0.8;
        }

        /* Content styling */
        #appointment-table tbody td > * {
            font-size: 0.9rem;
            color: var(--bs-body-color, #212529);
        }

        /* Appointment ID button - prominent styling */
        #appointment-table tbody td:first-child .btn {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            font-weight: 600;
            border-radius: 6px;
            width: fit-content;
        }

        /* Status badge styling - pill shape */
        #appointment-table .white-space {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: fit-content;
        }

        /* Action column - full width at bottom */
        #appointment-table tbody td[data-label="Action"] {
            grid-column: span 2;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, rgba(0,0,0,0.02) 0%, rgba(0,0,0,0.01) 100%);
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            justify-content: flex-start;
            align-items: center;
        }

        #appointment-table tbody td[data-label="Action"]::before {
            margin-bottom: 0;
            margin-right: 1rem;
        }

        /* DataTable controls styling */
        .booking-data-table .dataTable-top {
            margin-bottom: 1.5rem;
        }

        .booking-data-table .table-responsive {
            overflow-x: visible !important;
        }

        .booking-data-table .dataTable-bottom {
            margin-top: 1rem;
        }

        /* Dark mode support */
        [data-bs-theme="dark"] #appointment-table tbody tr {
            background: linear-gradient(135deg, var(--bs-dark-bg-subtle, #1e1e1e) 0%, #252525 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] #appointment-table tbody td {
            border-color: rgba(255, 255, 255, 0.05);
        }

        [data-bs-theme="dark"] #appointment-table tbody td:nth-child(1),
        [data-bs-theme="dark"] #appointment-table tbody td:nth-child(2) {
            background: rgba(99, 102, 241, 0.08);
        }

        [data-bs-theme="dark"] #appointment-table tbody td::before {
            color: var(--bs-gray-400, #adb5bd);
        }

        [data-bs-theme="dark"] #appointment-table tbody td[data-label="Action"] {
            background: rgba(0,0,0,0.15);
        }

        /* Responsive - single column on mobile */
        @media (max-width: 576px) {
            #appointment-table tbody tr {
                grid-template-columns: 1fr;
            }
            
            #appointment-table tbody td {
                border-right: none;
            }
            
            #appointment-table tbody td[data-label="Action"] {
                grid-column: span 1;
            }
        }

        /* Print styles - revert to table layout */
        @media print {
            #appointment-table thead {
                display: table-header-group;
            }

            #appointment-table tbody tr {
                display: table-row;
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                margin: 0;
            }

            #appointment-table tbody td {
                display: table-cell;
                border-bottom: 1px solid #ddd;
            }

            #appointment-table tbody td::before {
                display: none;
            }
        }
    </style>
@endpush
@if (false)
    @push('css')
        <link rel="stylesheet" href="{{ asset('packages/workdo/OutlookCalendar/src/Resources/assets/custom.css') }}">
    @endpush
@endif
@section('content')
    <div class="row">

        <div class="col-md-12">
            <div class="mt-2 " id="multiCollapseExample1">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center justify-content-end row-gaps">
                            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                <div class="btn-box">
                                    {!! Form::label('date', __('Date'), ['class' => 'form-label']) !!}
                                    {!! Form::date('date', $date ?? null, ['class' => 'form-control', 'required' => true]) !!}
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
                                <div class="btn-box">
                                    {!! Form::label('service', __('Service'), ['class' => 'form-label']) !!}
                                    {!! Form::select('service', $service ?? null, '', ['class' => 'form-control', 'required' => true]) !!}
                                </div>
                            </div>
                            <div class="col-lg-auto col-md-12 col-12  mt-lg-4 mt-1">
                                <div class="row header-btn-wrp">
                                    <div class="col-auto">
                                        <div class="d-flex">
                                            <a class="btn btn-sm btn-primary  me-2" data-bs-toggle="tooltip"
                                                title="{{ __('Apply') }}" id="applyfilter"
                                                data-original-title="{{ __('Apply') }}">
                                                <span class="btn-inner--icon d-flex align-items-center justify-center"><i
                                                        class="ti ti-search"></i></span>
                                            </a>
                                            <a href="#!" class="btn btn-sm btn-danger reset" data-bs-toggle="tooltip"
                                                title="{{ __('Reset') }}" id="clearfilter"
                                                data-original-title="{{ __('Reset') }}">
                                                <span class="btn-inner--icon d-flex align-items-center justify-center"><i
                                                        class="ti ti-refresh text-white-off "></i></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 col-md-12">
            <x-datatable :dataTable="$dataTable" />
        </div>
    </div>
@endsection

@push('scripts')
    {{--
    <script src="{{ asset('js/jquery.js') }}"></script> --}}
    <script src="{{ asset('assets/js/bootstrap-datepicker.js') }}"></script>
    <script>
        $(document).ready(function () {
            $(document).on('click', '#sendDataButton a', function (e) {
                e.preventDefault();

                var url = $(this).data('url');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                    },
                    beforeSend: function () {
                        $(".loader-wrapper").removeClass('d-none');
                    },
                    success: function (response) {
                        var appointment = response.data;
                        $(".loader-wrapper").addClass('d-none');
                        toastrs('Success', response.message, 'success');
                        location.reload();
                    },
                    error: function (xhr) {
                        $(".loader-wrapper").addClass('d-none');
                        toastrs('Error', xhr.responseJSON.error, 'error');
                    }
                });
            });

            // Add data-label attributes for card layout
            function addCardLabels() {
                var labels = ['No', 'Date/Duration', 'Customer', 'Staff', 'Service', 'Location', 'Payment', 'Status'];

                // Check if Rating column exists (AppointmentReview module)
                @if(false)
                    labels.push('Rating');
                @endif

                // Add Action label if user has permissions
                @if(\Laratrust::hasPermission(['additional quanitty edit', 'appointment edit', 'appointment delete']))
                    labels.push('Action');
                @endif

                $('#appointment-table tbody tr').each(function () {
                    $(this).find('td').each(function (index) {
                        if (labels[index]) {
                            $(this).attr('data-label', labels[index]);
                        }
                    });
                });
            }

            // Run on initial load
            $('#appointment-table').on('draw.dt', function () {
                addCardLabels();
            });

            // Also run immediately in case table is already loaded
            setTimeout(addCardLabels, 100);
        });
    </script>
@endpush

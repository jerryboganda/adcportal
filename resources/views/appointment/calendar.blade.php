@extends('layouts.main')

@section('page-title')
    {{ __('Appointment Calendar') }}
@endsection
@section('page-breadcrumb')
    {{ __('Appointment Calendar') }}
@endsection
@php
    $company_settings = getCompanyAllSetting();
@endphp
@section('page-action')
    <div class="d-flex gap-2">
        @stack('addButtonHook')
        @permission('appointment create')
            <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Create New Appointment') }}" data-url="{{ route('appointment.create') }}"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}" aria-label="{{ __('Create New Appointment') }}"><i class="ti ti-plus" aria-hidden="true"></i>
                <span>{{ __('Create') }}</span>
            </a>
        @endpermission
    </div>
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
@endpush
@section('content')
    <div class="row">
        <div class="col-lg-8 col-12">
            <div class="card appointment-calendar-wrp">
                <div class="card-header">
                    <div class="row row-gaps justify-content-between align-items-center">
                        <div class="col-xxl-auto col-12 ">
                            <h5>{{ __('Calendar') }}</h5>
                        </div>

                        <div class="col-xxl-auto col-12">
                            {{ Form::open(['route' => ['appointment.calendar'], 'method' => 'GET', 'id' => 'calendar_submit']) }}
                            <div class="gap-3 d-flex flex-wrap align-items-center justify-content-xxl-end justify-content-start">

                                {{ Form::label('calendar type', __('Calendar Type'), ['class' => 'text-type h6 mb-0']) }}
                                <div class="flex-wrap gap-2 d-flex radio-check">
                                    <div class="mb-0 form-check">
                                        <input type="radio" id="local_calendar" value="local_calendar"
                                            name="calendar_type" class="form-check-input code"
                                            {{ !isset($_GET['calendar_type']) || $_GET['calendar_type'] == 'local_calendar' ? "checked='checked'" : '' }}>
                                        <label class="form-check-label"
                                            for="local_calendar">{{ __('Local Calendar') }}</label>
                                    </div>
                                    @if (false && isset($company_settings['google_calendar_enable']))
                                        @if (isset($company_settings['google_calendar_enable']) && $company_settings['google_calendar_enable'] == 'on')
                                            <div class="mb-0 form-check">
                                                <input type="radio" id="google_calendar" value="google_calendar"
                                                    name="calendar_type" class="form-check-input code"
                                                    {{ isset($_GET['calendar_type']) && $_GET['calendar_type'] == 'google_calendar' ? "checked='checked'" : '' }}>
                                                <label class="form-check-label"
                                                    for="google_calendar">{{ __('Google Calendar') }}</label>
                                            </div>
                                        @endif
                                    @endif
                                    @if (false && isset($company_settings['outlook_calendar_enable']))
                                        @if (isset($company_settings['outlook_calendar_enable']) && $company_settings['outlook_calendar_enable'] == 'on')
                                            <div class="mb-0 form-check">
                                                <input type="radio" id="outlook_calendar" value="outlook_calendar"
                                                    name="calendar_type" class="form-check-input code"
                                                    {{ isset($_GET['calendar_type']) && $_GET['calendar_type'] == 'outlook_calendar' ? "checked='checked'" : '' }}>
                                                <label class="form-check-label"
                                                    for="outlook_calendar">{{ __('Outlook Calendar') }}</label>
                                            </div>
                                        @endif
                                    @endif
                                </div>


                                <div class="d-flex header-btn-wrp">
                                    <button type="submit" class="btn btn-sm btn-primary me-2"
                                        data-bs-toggle="tooltip" title="{{ __('Apply') }}">
                                        <i class="ti ti-search" aria-hidden="true"></i>
                                        <span>{{ __('Apply') }}</span>
                                    </button>
                                    <a href="{{ route('appointment.calendar') }}" class="btn btn-sm btn-danger"
                                        data-bs-toggle="tooltip" title="{{ __('Reset') }}">
                                        <i class="ti ti-refresh text-white" aria-hidden="true"></i>
                                        <span>{{ __('Reset') }}</span>
                                    </a>
                                </div>
                            </div>

                            {{ Form::close() }}
                        </div>

                    </div>
                </div>
                <div class="card-body p-4">
                    <div id='calendar' class='calendar' data-toggle="calendar"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-0">{{ __('Appointments') }}</h5>
                    <div class="card-body appointment-calendar-list p-0">
                    <ul class="event-cards list-group list-group-flush w-100 p-3">
                        @foreach ($appointments as $appointment)
                            @php
                                $date = !empty($appointment->start)
                                    ? $appointment->start
                                    : $appointment['start']->format('Y-m-d');
                                $end['end'] = Carbon\Carbon::parse($appointment['end']);
                                $month = date('m', strtotime($date));
                            @endphp
                            @if ($month == date('m'))
                                <li class="mb-3 list-group-item card">
                                    <div class="row align-items-center justify-content-between">
                                        <div class="col-auto mb-0">
                                            <div class="d-flex align-items-center">
                                                <div class="theme-avtar badge p-2 px-3 bg-primary">
                                                    <i class="ti ti-calendar" aria-hidden="true"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h6 class="m-0">
                                                        <div class="fc-daygrid-event sp-inheri">
                                                            <div class="fc-event-title-container">
                                                                <div class="fc-event-title text-dark">
                                                                    {{ $date }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </h6>
                                                    <small class="text-muted">
                                                        {{ !empty($appointment->time)
                                                            ? $appointment->time
                                                            : \Carbon\Carbon::parse($appointment['start'])->format('H:i') .
                                                                '-' .
                                                                \Carbon\Carbon::parse($appointment['end'])->format('H:i') }}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('/assets/js/calendar.js') }}"></script>
    <script type="text/javascript">
        (function() {
            var checkedValue = $('input[name="calendar_type"]:checked').val();
            var etitle;
            var etype;
            var etypeclass;
            var locale = "{{ app()->getLocale() }}";
            var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    timeGridDay: "{{ __('Day') }}",
                    timeGridWeek: "{{ __('Week') }}",
                    dayGridMonth: "{{ __('Month') }}"
                },
                locale: locale,
                themeSystem: 'bootstrap',
                slotDuration: '00:10:00',
                navLinks: true,
                droppable: true,
                selectable: true,
                selectMirror: true,
                editable: true,
                dayMaxEvents: true,
                handleWindowResize: true,
                firstDay: {{ $weekStartDay }},
                events: {!! json_encode($appointments) !!},
                eventClick: function(e) {
                    e.jsEvent.preventDefault();
                    var title = e.title;
                    var url = e.el.href;
                    var size = 'md';
                    $("#commonModal .modal-title").html('Appointment Details');
                    $("#commonModal .modal-dialog").addClass('modal-' + size);
                    $.ajax({
                        url: url,
                        success: function(data) {
                            $('#commonModal .body').html(data);
                            $("#commonModal").modal('show');
                            if (checkedValue === 'google_calendar') {
                                $("#commonModal").modal('hide');
                            } else if (checkedValue === 'outlook_calendar') {
                                $("#commonModal").modal('hide');
                            }
                            common_bind();
                        },
                        error: function(data) {
                            data = data.responseJSON;
                            toastrs('Error', data.error, 'error')
                        }
                    });
                }

            });
            calendar.render();
        })();
    </script>
@endpush

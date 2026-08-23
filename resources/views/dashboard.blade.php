@extends('layouts.main')
@section('page-title')
    {{ __('Dashboard') }}
@endsection
@section('content')
    <div class="row bookinggo-raw bookinggo-main-row overview-bookinggo-row">
        <div class="col-12">
            <div class="row bookinggo-dash-row justify-content-between align-items-center">
                <div class="d-flex bookinggo-row-inner col-md-10 mb-0">
                    <h5 class="h3 mb-0">{{ __('Dashboard') }}</h5>
                    {{-- Single-clinic app: business switcher removed. --}}
                    <span class="dash-head-link me-0 cust-btn">
                        <i class="ti ti-apps"></i>
                        <span class="hide-mob">{{ $business->name }}</span>
                    </span>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap row-gap mb-4 mt-3 bookinggo-card-wrapper ">
            <div class="col-12">
                <div class="row row-gap">
                    <div class="col-xxl-10 col-md-9 col-sm-8 col-12">
                        <div class="dashboard-card">
                            <img src="{{ asset('assets/images/layer.png') }}" class="dashboard-card-layer" alt="layer">
                            <div class="card-inner">
                                <div class="card-content">
                                    <h3>{{ __(Auth::user()->name) }}</h3>
                                    <p>{{ __('Your central hub for tracking, managing, and excelling — everything you need at your fingertips!') }}
                                    </p>
                                    <div class="btn-wrp d-flex flex-wrap gap-3">
                                        <a href="javascript:"
                                            class="btn btn-primary d-flex align-items-center gap-1 cp_link" tabindex="0"
                                            data-bs-whatever="{{ __('Copy Link') }}" data-bs-toggle="tooltip"
                                            data-bs-original-title="{{ __('Copy Link') }}"
                                            title="{{ __('Click to copy link') }}" id="cp_link"
                                            data-link="{{ route('find.appointment', $business->slug) }}"
                                            onclick="copy_link(this)">
                                            <i class="ti ti-link text-white"></i>
                                            <span> {{ __('Track Your Appointment') }}</span>
                                        </a>
                                        <a href="javascript:"
                                            class="btn btn-primary d-flex align-items-center gap-1 cp_link" tabindex="0"
                                            data-link="{{ route('appointments.form') }}"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ __('Click To Copy Link') }}">
                                            <i class="ti ti-link text-white"></i>
                                            <span>{{ __('Business Link') }}</span></a>
                                        <a href="javascript:" class="btn btn-primary socialShareButton" tabindex="0"
                                            id="socialShareButton" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ __('Click To Share Button') }}">
                                            <i class="ti ti-share text-white"></i>
                                        </a>
                                        <div id="sharingButtonsContainer" class="sharingButtonsContainer"
                                            style="display: none;">
                                            <div class="Demo1 d-flex align-items-center justify-content-center mb-0 hidden">
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="card-icon  d-flex align-items-center justify-content-center">
                                    <svg width="95" height="95" viewBox="0 0 95 95" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M76.5135 53.9287C66.8551 53.9287 59.0271 61.7786 59.0271 71.464C59.0271 81.1495 66.8551 88.9994 76.5135 88.9994C86.1719 88.9994 93.9999 81.1495 93.9999 71.464C93.9999 61.7786 86.1719 53.9287 76.5135 53.9287ZM84.2555 70.717L75.7177 77.6124C75.2083 78.0242 74.5716 78.2477 73.9222 78.2477C73.814 78.2477 73.7057 78.2413 73.5943 78.2285C72.8335 78.1423 72.1395 77.7529 71.6684 77.1463L68.3131 72.8559C67.3358 71.6045 67.5554 69.8008 68.7969 68.8208C70.048 67.8408 71.8403 68.061 72.8208 69.306L74.3838 71.3076L80.6615 66.235C81.8966 65.2359 83.6984 65.4402 84.6917 66.6724C85.6849 67.911 85.4907 69.721 84.2555 70.717Z"
                                            fill="#18BF6B" />
                                        <path opacity="0.6"
                                            d="M54.2523 71.4678C54.2523 59.1582 64.2386 49.144 76.5138 49.144C80.5917 49.144 84.4054 50.2677 87.6971 52.1958V23.4619C87.6971 16.2154 81.8396 10.3415 74.6133 10.3415H67.7372V8.87307C67.7372 7.27692 66.432 6 64.8721 6C63.2804 6 62.007 7.27692 62.007 8.87307V10.3415H27.7219V8.87307C27.7219 7.27692 26.4485 6 24.8568 6C23.2651 6 21.9917 7.27692 21.9917 8.87307V10.3415H15.0838C7.85745 10.3415 2 16.2154 2 23.4619V70.5164C2 77.763 7.85745 83.6368 15.0838 83.6368H57.875C55.5925 80.1317 54.2523 75.9561 54.2523 71.4678ZM65.4483 36.9462C67.2756 36.9462 68.7558 38.4306 68.7558 40.263C68.7558 42.0954 67.2756 43.5798 65.4483 43.5798C63.621 43.5798 62.1408 42.0954 62.1408 40.263C62.1408 38.4306 63.621 36.9462 65.4483 36.9462ZM24.2647 61.6514C22.4374 61.6514 20.9571 60.167 20.9571 58.3346C20.9571 56.5022 22.4374 55.0178 24.2647 55.0178C26.092 55.0178 27.5722 56.5022 27.5722 58.3346C27.5722 60.167 26.092 61.6514 24.2647 61.6514ZM24.2647 43.5798C22.4374 43.5798 20.9571 42.0954 20.9571 40.263C20.9571 38.4306 22.4374 36.9462 24.2647 36.9462C26.092 36.9462 27.5722 38.4306 27.5722 40.263C27.5722 42.0954 26.092 43.5798 24.2647 43.5798ZM44.8581 61.6514C43.0308 61.6514 41.5505 60.167 41.5505 58.3346C41.5505 56.5022 43.0308 55.0178 44.8581 55.0178C46.6854 55.0178 48.1656 56.5022 48.1656 58.3346C48.1656 60.167 46.6822 61.6514 44.8581 61.6514ZM44.8581 43.5798C43.0308 43.5798 41.5505 42.0954 41.5505 40.263C41.5505 38.4306 43.0308 36.9462 44.8581 36.9462C46.6854 36.9462 48.1656 38.4306 48.1656 40.263C48.1656 42.0954 46.6822 43.5798 44.8581 43.5798Z"
                                            fill="#18BF6B" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-md-3 col-sm-4 col-12 ">
                        <div class="qr-card">
                            <div class="qr-card-inner">
                                <div class="shareqrcode">
                                    {!! QrCode::generate(route('appointments.form')) !!}
                                </div>
                                <div class="qr-card-content">
                                    <div class="qr-btn">
                                        <span>{{ $business->name }}</span>
                                        <h3 id="greetings" style="display: none;"></h3>
                                        <a href="#" class="cp_link" tabindex="0"
                                            data-link="{{ route('appointments.form') }}"
                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="{{ __('Click To Copy Link') }}">
                                            <i class="ti ti-layers-linked text-primary"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap row-gap mb-1 mt-1 bookinggo-infocard-wrapper">
            <div class="col-12">
                <div class="row dashboard-wrp">
                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-briefcase text-danger"></i>

                                    </div>
                                    <a href="{{ route('business.index') }}">
                                        <h3 class="mt-3 mb-0 text-danger">{{ __('Total Business') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ $total_business }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <a href="{{ route('appointment.index') }}">
                                        <h3 class="mt-3 mb-0">{{ __('Total Appointment') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ $total_appointment }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <a href="{{ route('appointment.index') }}">
                                        <h3 class="mt-3 mb-0">{{ __('Total Revenue') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ currency_format_with_sym($total_appointment_payment) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-user-friends"></i>
                                    </div>
                                    <a href="{{ route('business.manage', getActiveBusiness()) }}">
                                        <h3 class="mt-3 mb-0">{{ __('Total Staff') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ $total_staff }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-user-cog"></i>
                                    </div>
                                    <a href="{{ route('business.manage', getActiveBusiness()) }}">
                                        <h3 class="mt-3 mb-0">{{ __('Total Service') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ $total_service }}</h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-4 col-12">
                        <div class="dashboard-project-card">
                            <div class="card-inner flex-wrap gap-3 d-flex justify-content-between">
                                <div class="card-content">
                                    <div class="theme-avtar bg-white">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <a href="{{ route('business.manage', getActiveBusiness()) }}">
                                        <h3 class="mt-3 mb-0">{{ __('Total Location') }}</h3>
                                    </a>
                                </div>
                                <h3 class="mb-0">{{ $total_location }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="col-sm-12">
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="row g-3 mt-2">
                        <div class="col-xl-6">
                            <div class="theme-card dashboard-theme-card card p-3 mb-0">
                                <div class="theme-image">
                                    <span class="badges bg-success">{{ __('Current Business') }}</span>
                                    @if ($business->form_type == 'website')
                                        <img src="{{ get_module_card_img($business->layouts) }}" alt="theme-image">
                                    @else
                                        <img src="{{ asset(get_file('form_layouts/' . $business->layouts . '/images/form.png')) }}"
                                            alt="theme-image">
                                    @endif
                                </div>
                                <div class="theme-bottom-content">
                                    <div class="theme-card-lable">
                                        <b class="h6">{{ $business->name }}</b>
                                    </div>
                                    <div class="theme-card-button ">
                                        <a class="btn btn-sm btn2 btn-primary text-end"
                                            href="{{ route('business.manage', $business->id) }}">
                                            {{ __('Edit Business') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card overflow-auto card-dash">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5>{{ __('Recent Appointment') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart">
                                        <div id="appointment-chart" data-color="primary" data-height="280"
                                            class="p-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">

                        <div class="col-12">
                            <div id="embedded-code-sidenav" class="card mb-0">
                                <div class="card-header">
                                    <h5>{{ __('Embedded Code') }}</h5>
                                    <small class="text-muted">{{ __('Copy this code and put anywhere') }}</small>
                                </div>
                                <div class="bg-none">
                                    <div class="row company-setting">
                                        <div class="">
                                            <form id="setting-form" method="post" action="#"
                                                enctype="multipart/form-data">
                                                @csrf
                                                <div class="card-header card-body">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="form-group mb-0">
                                                                {{ Form::label('embedded_code', __('Embedded Code'), ['class' => 'form-label']) }}
                                                                {{ Form::textarea('embedded_code', EmbeddedCode($business), ['class' => 'form-control', 'rows' => '1', 'readonly']) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-xl-4 col-12">
                            <div class="card overflow-auto card-dash">
                                <div class="card-header">
                                    <h5>{{ __('Latest Service') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive common-scroll" style="max-height: 390px; overflow:auto;">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50%">{{ __('Name') }}</th>
                                                    {{-- <th>{{ __('Price') }}</th> --}}
                                                    <th style="width: 50%">{{ __('Duration') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($latest_services as $latest_service)
                                                    @php
                                                        $valuesString = '';
                                                        if (false) {
                                                            $valuesString = $latest_service->service_id;
                                                        } elseif (false) {
                                                            $valuesString = $latest_service->collaborative_service_id;
                                                        }
                                                        $valuesString = trim($valuesString);

                                                        if (!empty($valuesString)) {
                                                            $valuesArray = explode(',', $valuesString);
                                                            $valuesArray = array_map('trim', $valuesArray);
                                                            $count = count($valuesArray);
                                                        } else {
                                                            $count = 0;
                                                        }

                                                        $currency_symbol_position =
                                                            $getCurrencyFormatSymbol['currency_symbol_position'];
                                                        $currancy_symbol = $getCurrencyFormatSymbol['currancy_symbol'];
                                                        $currancy_format = $getCurrencyFormatSymbol['currancy_format'];
                                                        // $servicePrice = number_format($latest_service->price,$currancy_format,);
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="service-tbl-img">

                                                                    <img src="{{ check_file($latest_service->image) ? get_file($latest_service->image) : get_file('uploads/default/avatar.png') }}"
                                                                        class="img-fluid rounded border-2 border border-primary"
                                                                        width="45"
                                                                        style="height: 45px; min-width:35px">
                                                                </div>
                                                                <div class="d-flex flex-column">
                                                                    <p class="mb-0">{{ $latest_service->name }}</p>
                                                                    <div>
                                                                        <ins>
                                                                            <b>
                                                                                {{ super_currency_format_with_sym($latest_service->price) }}
                                                                            </b>
                                                                        </ins>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        {{-- <td>{{ $latest_service->price }}</td> --}}
                                                        <td>
                                                            @if (
                                                                (false && $latest_service->service_type == 'compound') ||
                                                                    (false && $latest_service->service_type == 'collaborative'))
                                                                {{ $count . ' Services' }}
                                                            @else
                                                                {{ $latest_service->duration }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-12">
                            <div class="card overflow-auto card-dash">
                                <div class="card-header">
                                    <h5>{{ __('Clinic') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive common-scroll h-100"
                                        style="max-height: 390px; overflow:auto;">
                                        <table class="table h-100">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50%">{{ __('Name') }}</th>
                                                    <th style="width: 50%">{{ __('Action') }}</th>
                                                </tr>
                                            </thead>

                                            <tbody class="h-100">
                                                @foreach ($latest_businesses as $latest_business)
                                                    <tr>
                                                        <td><a
                                                                href="{{ route('business.manage', $latest_business->id) }}">{{ $latest_business->name }}</a>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="action-btn">
                                                                    <a href="{{ route('appointments.form') }}"
                                                                        class="btn btn-sm  bg-primary align-items-center cp_link"
                                                                        data-link="{{ route('appointments.form') }}"
                                                                        data-bs-placement="bottom"
                                                                        data-bs-toggle="tooltip"
                                                                        data-bs-original-title="{{ __('Click To Copy Form Link') }}">
                                                                        <i class="ti ti-link text-white"></i>
                                                                    </a>
                                                                </div>
                                                                @permission('business update')
                                                                    <div class="action-btn">
                                                                        <a href="{{ route('business.manage', $latest_business->id) }}"
                                                                            class="btn btn-sm  bg-info align-items-center"
                                                                            data-bs-toggle="tooltip" title='Manage Clinic'>
                                                                            <span class="text-white"> <i
                                                                                    class="ti ti-corner-up-left"></i></span></a>
                                                                    </div>
                                                                @endpermission
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-12">
                            <div class="card overflow-auto card-dash">
                                <div class="card-header">
                                    <h5>{{ __('Appointments by Service') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive common-scroll" style="max-height: 390px; overflow:auto;">
                                        <div id="progressBars"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 mb-4 bookinggo-card">
            <div class="d-flex justify-content-between align-items-center mb-3 ">
                <h5 class="m-0">{{ __('Latest Appointment') }}</h5>
                <a class="btn btn-primary" href="{{ route('appointment.index') }}">{{ __('View All') }}</a>
            </div>
            <div class="row">
                @foreach ($latest_appointments as $latest_appointment)
                    <div class="col-xxl-3 col-lg-4 col-md-6 col-sm-6 col-12 d-flex ">
                        <div class="card appointment-card mb-0 w-100">
                            <div class="appointment-card-top text-center">
                                <div class="appointment-card-img rounded border-2 border border-primary">
                                    <img class="img-fluid"
                                        src="{{ (!empty($latest_appointment->StaffData) && check_file($latest_appointment->StaffData->user->avatar)) ? get_file($latest_appointment->StaffData->user->avatar) : get_file('uploads/default/avatar.png') }}">
                                </div>
                                <div class="appointment-content">
                                    <h4>{{ !empty($latest_appointment->StaffData) ? $latest_appointment->StaffData->name : '-' }}
                                    </h4>
                                    <p class="mb-0">
                                        {{ !empty($latest_appointment->StaffData) && !empty($latest_appointment->StaffData->user) ? $latest_appointment->StaffData->user->email : '-' }}
                                    </p>
                                </div>
                                <ul class="appointment-card-info d-flex justify-content-center align-items-center ">
                                    <li class="location d-flex align-items-center ">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                            fill="#000000" version="1.1" id="Capa_1" width="20px" height="20px"
                                            viewBox="0 0 395.71 395.71" xml:space="preserve">
                                            <g>
                                                <path
                                                    d="M197.849,0C122.131,0,60.531,61.609,60.531,137.329c0,72.887,124.591,243.177,129.896,250.388l4.951,6.738   c0.579,0.792,1.501,1.255,2.471,1.255c0.985,0,1.901-0.463,2.486-1.255l4.948-6.738c5.308-7.211,129.896-177.501,129.896-250.388   C335.179,61.609,273.569,0,197.849,0z M197.849,88.138c27.13,0,49.191,22.062,49.191,49.191c0,27.115-22.062,49.191-49.191,49.191   c-27.114,0-49.191-22.076-49.191-49.191C148.658,110.2,170.734,88.138,197.849,88.138z" />
                                            </g>
                                        </svg>
                                        <span>{{ !empty($latest_appointment->LocationData) ? $latest_appointment->LocationData->name : '-' }}</span>
                                    </li>
                                    <li class="status d-flex align-items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" width="30px"
                                            height="30px">
                                            <path
                                                d="M15,3C8.373,3,3,8.373,3,15c0,6.627,5.373,12,12,12s12-5.373,12-12C27,8.373,21.627,3,15,3z M21.707,12.707l-7.56,7.56 c-0.188,0.188-0.442,0.293-0.707,0.293s-0.52-0.105-0.707-0.293l-3.453-3.453c-0.391-0.391-0.391-1.023,0-1.414s1.023-0.391,1.414,0 l2.746,2.746l6.853-6.853c0.391-0.391,1.023-0.391,1.414,0S22.098,12.316,21.707,12.707z" />
                                        </svg>
                                        <span
                                            style="background-color: #{{ !empty($latest_appointment->StatusData->status_color) ? $latest_appointment->StatusData->status_color : 'a2e0c2' }};">{{ !empty($latest_appointment->StatusData) ? $latest_appointment->StatusData->title : (false && $latest_appointment->appointment_status == 'Waiting List' ? $latest_appointment->appointment_status : 'Pending') }}</span>
                                    </li>
                                </ul>
                                <ul class="appointment-card-btn d-flex align-items-center justify-content-center">
                                    <li>
                                        <a href="{{ route('appointment.calendar') }}" data-bs-toggle="tooltip"
                                            data-bs-original-title="{{ __('Appointment Calendar') }}"
                                            class="btn btn-sm  bg-primary">
                                            <svg fill="#000000" version="1.1" id="Capa_1"
                                                xmlns="http://www.w3.org/2000/svg"
                                                xmlns:xlink="http://www.w3.org/1999/xlink" width="64px" height="64px"
                                                viewBox="0 0 610.398 610.398" xml:space="preserve">
                                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round"
                                                    stroke-linejoin="round"></g>
                                                <path
                                                    d="M159.567,0h-15.329c-1.956,0-3.811,0.411-5.608,0.995c-8.979,2.912-15.616,12.498-15.616,23.997v10.552v27.009v14.052 c0,2.611,0.435,5.078,1.066,7.44c2.702,10.146,10.653,17.552,20.158,17.552h15.329c11.724,0,21.224-11.188,21.224-24.992V62.553 V35.544V24.992C180.791,11.188,171.291,0,159.567,0z">
                                                </path>
                                                <path
                                                    d="M461.288,0h-15.329c-11.724,0-21.224,11.188-21.224,24.992v10.552v27.009v14.052c0,13.804,9.5,24.992,21.224,24.992 h15.329c11.724,0,21.224-11.188,21.224-24.992V62.553V35.544V24.992C482.507,11.188,473.007,0,461.288,0z">
                                                </path>
                                                <path
                                                    d="M539.586,62.553h-37.954v14.052c0,24.327-18.102,44.117-40.349,44.117h-15.329c-22.247,0-40.349-19.79-40.349-44.117 V62.553H199.916v14.052c0,24.327-18.102,44.117-40.349,44.117h-15.329c-22.248,0-40.349-19.79-40.349-44.117V62.553H70.818 c-21.066,0-38.15,16.017-38.15,35.764v476.318c0,19.784,17.083,35.764,38.15,35.764h468.763c21.085,0,38.149-15.984,38.149-35.764 V98.322C577.735,78.575,560.671,62.553,539.586,62.553z M527.757,557.9l-446.502-0.172V173.717h446.502V557.9z">
                                                </path>
                                                <path
                                                    d="M353.017,266.258h117.428c10.193,0,18.437-10.179,18.437-22.759s-8.248-22.759-18.437-22.759H353.017 c-10.193,0-18.437,10.179-18.437,22.759C334.58,256.074,342.823,266.258,353.017,266.258z">
                                                </path>
                                                <path
                                                    d="M353.017,348.467h117.428c10.193,0,18.437-10.179,18.437-22.759c0-12.579-8.248-22.758-18.437-22.758H353.017 c-10.193,0-18.437,10.179-18.437,22.758C334.58,338.288,342.823,348.467,353.017,348.467z">
                                                </path>
                                                <path
                                                    d="M353.017,430.676h117.428c10.193,0,18.437-10.18,18.437-22.759s-8.248-22.759-18.437-22.759H353.017 c-10.193,0-18.437,10.18-18.437,22.759S342.823,430.676,353.017,430.676z">
                                                </path>
                                                <path
                                                    d="M353.017,512.89h117.428c10.193,0,18.437-10.18,18.437-22.759c0-12.58-8.248-22.759-18.437-22.759H353.017 c-10.193,0-18.437,10.179-18.437,22.759C334.58,502.71,342.823,512.89,353.017,512.89z">
                                                </path>
                                                <path
                                                    d="M145.032,266.258H262.46c10.193,0,18.436-10.179,18.436-22.759s-8.248-22.759-18.436-22.759H145.032 c-10.194,0-18.437,10.179-18.437,22.759C126.596,256.074,134.838,266.258,145.032,266.258z">
                                                </path>
                                                <path
                                                    d="M145.032,348.467H262.46c10.193,0,18.436-10.179,18.436-22.759c0-12.579-8.248-22.758-18.436-22.758H145.032 c-10.194,0-18.437,10.179-18.437,22.758C126.596,338.288,134.838,348.467,145.032,348.467z">
                                                </path>
                                                <path
                                                    d="M145.032,430.676H262.46c10.193,0,18.436-10.18,18.436-22.759s-8.248-22.759-18.436-22.759H145.032 c-10.194,0-18.437,10.18-18.437,22.759S134.838,430.676,145.032,430.676z">
                                                </path>
                                                <path
                                                    d="M145.032,512.89H262.46c10.193,0,18.436-10.18,18.436-22.759c0-12.58-8.248-22.759-18.436-22.759H145.032 c-10.194,0-18.437,10.179-18.437,22.759C126.596,502.71,134.838,512.89,145.032,512.89z">
                                                </path>
                                            </svg>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0)"
                                            data-url="{{ route('appointment.show', $latest_appointment->id) }}"
                                            data-size="lg" data-ajax-popup="true"
                                            data-title="{{ __('Appointment Details') }}" data-bs-toggle="tooltip"
                                            data-bs-original-title="{{ __('Appointment Details') }}"
                                            class="btn btn-sm  bg-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" height="512" viewBox="0 0 96 96"
                                                width="512">
                                                <g id="Layer_4">
                                                    <g>
                                                        <path
                                                            d="m18.294 92h10.893c1.711 0 3.099-1.289 3.099-3s-1.387-3-3.099-3h-10.893c-4.586 0-8.294-4.07-8.294-8.831v-44.169h75v11.282c0 1.711 1.789 3.099 3.5 3.099s3.5-1.387 3.5-3.099v-25.388c0-8.073-7.587-14.894-15.574-14.894h-58.132c-8.003 0-14.294 6.681-14.294 14.894v58.275c0 8.178 6.291 14.831 14.294 14.831zm-8.294-73.106c0-4.714 3.786-8.894 8.294-8.894h58.132c4.627 0 8.574 4.18 8.574 8.894v8.106h-75z" />
                                                        <path
                                                            d="m74.427 16h-1.239c-1.711 0-3.099 1.789-3.099 3.5s1.387 3.5 3.099 3.5h1.239c1.711 0 3.099-1.789 3.099-3.5s-1.388-3.5-3.099-3.5z" />
                                                        <path
                                                            d="m63.892 16h-1.239c-1.711 0-3.099 1.789-3.099 3.5s1.387 3.5 3.099 3.5h1.239c1.711 0 3.099-1.789 3.099-3.5s-1.388-3.5-3.099-3.5z" />
                                                        <path
                                                            d="m52.737 16h-1.239c-1.711 0-3.099 1.789-3.099 3.5s1.387 3.5 3.099 3.5h1.239c1.711 0 3.099-1.789 3.099-3.5s-1.388-3.5-3.099-3.5z" />
                                                        <path
                                                            d="m60.997 47.135c-13.588 0-25.997 8.533-30.997 21.234 0 0 6.505 23.954 30.997 23.432 25.878-.551 31.003-23.432 31.003-23.432-5-12.701-17.411-21.234-31.003-21.234zm.221 38.469c-10.634 0-20.346-6.437-24.536-16.136 4.189-9.699 13.902-16.136 24.536-16.136 10.637 0 20.351 6.437 24.541 16.136-4.189 9.699-13.904 16.136-24.541 16.136z" />
                                                        <path
                                                            d="m61.221 57.445c-6.63 0-12.024 5.393-12.024 12.023s5.394 12.023 12.024 12.023 12.023-5.393 12.023-12.023-5.393-12.023-12.023-12.023zm0 17.849c-3.213 0-5.827-2.614-5.827-5.826s2.614-5.826 5.827-5.826 5.826 2.614 5.826 5.826-2.613 5.826-5.826 5.826z" />
                                                    </g>
                                                </g>
                                            </svg> </a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0)"
                                            data-url="{{ route('appointment.status.change', $latest_appointment->id) }}"
                                            data-ajax-popup="true" data-title="{{ __('Update Status') }}"
                                            data-bs-toggle="tooltip" data-bs-original-title="{{ __('Update Status') }}"
                                            class="btn btn-sm  bg-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" clip-rule="evenodd"
                                                fill-rule="evenodd" height="512" stroke-linejoin="round"
                                                stroke-miterlimit="2" viewBox="0 0 48 48" width="512">
                                                <g transform="translate(-159 -265)">
                                                    <g transform="translate(-769.565 -100.879)">
                                                        <g id="ngicon">
                                                            <path
                                                                d="m955.996 405.928c-1.107.234-2.255.357-3.431.357-9.107 0-16.5-7.393-16.5-16.5 0-1.379-1.12-2.5-2.5-2.5s-2.5 1.121-2.5 2.5c0 11.867 9.634 21.5 21.5 21.5 1.531 0 3.025-.16 4.466-.465 1.35-.286 2.214-1.614 1.928-2.964-.285-1.35-1.613-2.214-2.963-1.928zm-1.106-32.48c8.008 1.13 14.175 8.02 14.175 16.337 0 5.884-3.087 11.053-7.728 13.975-1.168.735-1.519 2.28-.783 3.447.735 1.168 2.28 1.519 3.447.784 6.045-3.807 10.064-10.541 10.064-18.206 0-10.839-8.04-19.815-18.476-21.288-1.366-.193-2.632.76-2.825 2.126s.76 2.632 2.126 2.825zm-17.638 10.183c1.768-4.389 5.37-7.846 9.854-9.42 1.302-.457 1.987-1.885 1.53-3.187s-1.885-1.988-3.186-1.531c-5.841 2.05-10.532 6.553-12.836 12.27-.515 1.28.105 2.738 1.385 3.253 1.28.516 2.738-.105 3.253-1.385z" />
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg> </a>
                                    </li>

                                </ul>
                            </div>
                            <div class="appointment-card-bottom">
                                <div class="doctor text-center">
                                    <h4>{{ !empty($latest_appointment->ServiceData) ? $latest_appointment->ServiceData->name : '-' }}
                                    </h4>
                                    <p class="mb-0">
                                        {{ App\Models\Appointment::appointmentNumberFormat($latest_appointment->id, $latest_appointment->created_by, $latest_appointment->business_id) }}
                                    </p>
                                </div>
                                <div class="date-wrp d-flex">
                                    <div class="date">
                                        <span>{{ __('Date') }}</span>
                                        <h5 class="mb-0">{{ $latest_appointment->date }}</h5>
                                    </div>
                                    <div class="date time">
                                        <span>{{ __('Time') }}</span>
                                        <h5 class="mb-0">{{ $latest_appointment->time }}</h5>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex mb-3 align-items-center justify-content-between">
            <h5 class="m-0">{{ __('Analytics Report') }}</h5>
            <div class="mr-lg-2">
                <div class="dropdown analytics-dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="staffFilterDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        {{ __('Select Staff') }}
                    </button>
                    <div class="dropdown-menu p-3" aria-labelledby="staffFilterDropdown">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">
                                {{ __('Select All') }}
                            </label>
                        </div>
                        @foreach ($staffs as $staff)
                            <div class="form-check mb-2">
                                <input class="form-check-input staff-checkbox staff-select" type="checkbox"
                                    value="{{ $staff->user_id }}" id="staff{{ $staff->user_id }}"
                                    onclick="filterTable()" name="staff[]">
                                <label class="form-check-label" for="staff{{ $staff->user_id }}">
                                    {{ $staff->name }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="app-table-wrp mt-0 common-scroll">
            <table class="table table-striped table-bordered appointment-table shadow-none">
                <thead>
                    <tr>
                        <th rowspan="2" scope="col">{{ __('Staff') }}</th>
                        <th rowspan="2" scope="col">{{ __('Appointment') }}</th>
                        <th colspan="{{ $statuses->count() }}" scope="col" class="text-center">{{ __('Status') }}
                        </th>
                        <th rowspan="2" scope="col">{{ __('Revenue') }}</th>
                    </tr>
                    <tr>
                        @foreach ($statuses as $status)
                            <th scope="col">{{ $status->title }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    @foreach ($staffs as $staff)
                        @php
                            $totalAppointmentsForStaff = isset($staffStatusAppointments[$staff->user_id])
                                ? $staffStatusAppointments[$staff->user_id]->sum('appointment_count')
                                : 0;

                            $totalRevenueForStaff = isset($staffStatusAppointments[$staff->user_id])
                                ? $staffStatusAppointments[$staff->user_id]->sum('total_revenue')
                                : 0;
                        @endphp
                        <tr data-staff-id="{{ $staff->user_id }}">
                            <td>{{ $staff->name }}</td>
                            <td>{{ $totalAppointmentsForStaff }}</td>
                            @foreach ($statuses as $status)
                                @php
                                    $statusCount = isset($staffStatusAppointments[$staff->user_id][$status->id])
                                        ? $staffStatusAppointments[$staff->user_id][$status->id]->appointment_count
                                        : 0;
                                @endphp
                                <td>{{ $statusCount }}</td>
                            @endforeach
                            <td>{{ currency_format_with_sym($totalRevenueForStaff, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>{{ __('Total :') }}</th>
                        <th id="totalAppointments">{{ $totalAppointments }}</th>
                        @foreach ($statuses as $status)
                            @php
                                $statusTotalCount = $staffStatusAppointments
                                    ->flatMap(function ($appointments) use ($status) {
                                        return $appointments->where('appointment_status', $status->id);
                                    })
                                    ->sum('appointment_count');
                                if ($status->id === 0) {
                                    $statusTotalCount += $staffStatusAppointments
                                        ->flatMap(function ($appointments) {
                                            return $appointments->where('appointment_status', 'Pending');
                                        })
                                        ->sum('appointment_count');
                                }
                            @endphp
                            <th class="status-total">{{ $statusTotalCount }}</th>
                        @endforeach
                        <th id="totalRevenue">
                            {{ currency_format_with_sym($totalRevenue, 2) }}
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/plugins/apexcharts.js') }}"></script>
    <script>
        "use strict";
        $(document).ready(function() {
            $('.cp_link').on('click', function() {
                var value = $(this).attr('data-link');
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(value).select();
                document.execCommand("copy");
                $temp.remove();
                toastrs('Success', '{{ __('Link copied') }}', 'success')
            });
        });

        var today = new Date()
        var curHr = today.getHours()
        var target = document.getElementById("greetings");

        if (curHr < 12) {
            target.innerHTML = "{{ __('Good Morning,') }}";
        } else if (curHr < 17) {
            target.innerHTML = "{{ __('Good Afternoon,') }}";
        } else {
            target.innerHTML = "{{ __('Good Evening,') }}";
        }

        (function() {
            var chartBarOptions = {
                series: [{
                    name: '{{ __('Appointment') }}',
                    data: {!! json_encode($chartData['data']) !!},
                }, ],

                chart: {
                    height: 300,
                    type: 'area',
                    dropShadow: {
                        enabled: true,
                        color: '#000',
                        top: 18,
                        left: 7,
                        blur: 10,
                        opacity: 0.2
                    },
                    toolbar: {
                        show: false
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: 2,
                    curve: 'smooth'
                },
                title: {
                    text: '',
                    align: 'left'
                },
                xaxis: {
                    categories: {!! json_encode($chartData['label']) !!},
                    title: {
                        text: '{{ __('Days') }}'
                    }
                },
                colors: ['#6fd944', '#6fd944'],

                grid: {
                    strokeDashArray: 4,
                },
                legend: {
                    show: false,
                },
                yaxis: {
                    title: {
                        text: '{{ __('Appointment') }}'
                    },

                }

            };
            var arChart = new ApexCharts(document.querySelector("#appointment-chart"), chartBarOptions);
            arChart.render();
        })();

        $(document).ready(function() {
            var customURL = '{{ route('appointments.form') }}';
            $('.Demo1').socialSharingPlugin({
                url: customURL,
                title: $('meta[property="og:title"]').attr('content'),
                description: $('meta[property="og:description"]').attr('content'),
                img: $('meta[property="og:image"]').attr('content'),
                enable: ['whatsapp', 'facebook', 'twitter', 'pinterest', 'instagram']
            });

            $('.socialShareButton').click(function(e) {
                e.preventDefault();
                $('.sharingButtonsContainer').toggle();
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('#sendDataButton a').click(function(e) {
                e.preventDefault();

                var url = $(this).data('url');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                    },
                    beforeSend: function() {
                        $(".loader-wrapper").removeClass('d-none');
                    },
                    success: function(response) {
                        var appointment = response.data;
                        $(".loader-wrapper").addClass('d-none');
                        toastrs('Success', response.message, 'success');
                        location.reload();
                    },
                    error: function(xhr) {
                        $(".loader-wrapper").addClass('d-none');
                        toastrs('Error', xhr.responseJSON.error, 'error');
                    }
                });
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            $('input[name="staff[]"]').change(function() {
                filterTable();
            });

            $('#selectAll').change(function() {
                $('.staff-checkbox').prop('checked', $(this).prop('checked'));
                filterTable();
            });

            function filterTable() {
                const checkedBoxes = document.querySelectorAll('.staff-checkbox:checked');
                const staffIds = Array.from(checkedBoxes).map(cb => cb.value);

                $.ajax({
                    url: "{{ route('dashboard.index') }}",
                    method: 'POST',
                    data: {
                        options: staffIds,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        $('#staffTableBody').empty().html(response.html);
                        updateTotals();
                    }
                });
            }

            function updateTotals() {
                let totalAppointments = 0;
                let totalRevenue = 0;
                const statusTotals = {};

                const rows = document.querySelectorAll('#staffTableBody tr');
                rows.forEach(row => {
                    const appointments = parseInt(row.cells[1].innerText) || 0;
                    const revenue = parseFloat(row.cells[row.cells.length - 1].innerText.replace(/[^\d.-]/g,
                        '')) || 0;
                    totalAppointments += appointments;
                    totalRevenue += revenue;

                    row.querySelectorAll('td').forEach((cell, index) => {
                        if (index > 1 && index < row.cells.length - 1) {
                            const status = parseInt(cell.innerText) || 0;
                            statusTotals[index] = (statusTotals[index] || 0) + status;
                        }
                    });
                });

                document.getElementById('totalAppointments').innerText = totalAppointments;
                document.getElementById('totalRevenue').innerText =
                    '{{ company_setting('defult_currancy_symbol') }}' + totalRevenue.toFixed(2);
                document.querySelectorAll('.status-total').forEach((cell, index) => {
                    cell.innerText = statusTotals[index + 2] || 0;
                });
            }
        });
    </script>
    <script>
        const services = @json($servicesChart);
        const container = document.getElementById("progressBars");
    
        const maxVal = Math.max(...services.map(s => s.value));
    
        services.forEach(service => {
            const percentageWidth = (service.value / maxVal) * 100;
    
            const bar = document.createElement("div");
            bar.className = "bar";
            bar.style.marginBottom = "16px"; // spacing between bars
    
            bar.innerHTML = `
                <div class="bar-label">${service.name}</div>
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <div class="bar-wrapper">
                        <div class="bar-fill"
                            style="width: ${percentageWidth}%; background: ${service.color};">
                        </div>
                    </div>
                    <div class="bar-count">${service.value}</div>
                </div>
            `;
            container.appendChild(bar);
        });
    </script>
@endpush

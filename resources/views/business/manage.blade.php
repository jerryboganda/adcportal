@extends('layouts.main')

@section('page-title')
    {{ __('Manage Business') }}
@endsection
@section('page-breadcrumb')
    {{ $business->name }},
    {{ __('Manage Business') }}
@endsection
@section('page-action')
<div class="d-flex">
    <a href="#" class="btn btn-sm btn-primary cp_link me-2" data-link="{{ route('appointments.form') }}"
            data-bs-placement="top" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Click To Copy Form Link') }}">
            <i class="text-white ti ti-link"></i>
        </a>
        @permission('subscriber manage')
            <a href="{{ route('subscribes.index', ['business' => $business->id]) }}" class="btn btn-sm bg-warning-subtle me-2"
                data-bs-toggle="tooltip" title='{{ __('Subscribers')}}'> <span class="text-white"> <i class="ti ti-mail"></i></span></a>
        @endpermission
        @permission('contact manage')
            <a href="{{ route('contacts.index', ['business' => $business->id]) }}" class="btn btn-sm bg-warning me-2"
                data-bs-toggle="tooltip" title='{{ __('Contacts')}}'> <span class="text-white"> <i class="ti ti-phone"></i></span></a>
        @endpermission
        @permission('appointment manage')
        <a href="{{ route('appointment.index', ['business' => $business->id]) }}" class="btn btn-sm bg-secondary"
            data-bs-toggle="tooltip"  title="{{ __('Appointments') }}"> <span class="text-white"> <i
                    class="ti ti-credit-card"></i></span></a>
        @endpermission
    </div>
@endsection

@push('css')
    @if (false)
        <link rel="stylesheet" href="{{ asset('packages/adc/PWA/src/Resources/assets/css/pwa.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
    @if (false)
        <link rel="stylesheet" href="{{ asset('packages/adc/FlexibleHours/src/Resources/assets/custom.css') }}">
    @endif
    @if (false)
        <link rel="stylesheet" href="{{ asset('packages/adc/FlexibleDays/src/Resources/assets/custom.css') }}">
    @endif
    @if (false)
        <link rel="stylesheet" href="{{ asset('packages/adc/AdditionalCustomField/src/Resources/assets/custom.css') }}">
    @endif


@endpush



@section('content')
    <div class="pt-3 page-header">
        <div class="page-block">
            <div class="row mt-md-4 row-gaps align-items-center bookinggo-dash-row manage-business-sec">
                {{-- Business switcher removed — single-clinic English-only --}}
                <div class="col-12">
                    <ul class="nav nav-pills nav-fill information-tab" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (!session('tab') or session('tab') and session('tab') == 12) active @endif" id="theme-setting-tab"
                                data-bs-toggle="pill" data-bs-target="#theme-setting"
                                type="button">{{ __('Theme') }}</button>
                        </li>

                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 1) active @endif"
                                id="location-setting-tab" data-bs-toggle="pill" data-bs-target="#location-setting"
                                type="button">{{ __('Location') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 5) active @endif" id="seo-setting-tab"
                                data-bs-toggle="pill" data-bs-target="#seo-setting"
                                type="button">{{ __('Category') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 2) active @endif"
                                id="details-setting-tab" data-bs-toggle="pill" data-bs-target="#details-setting"
                                type="button">{{ __('Service') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 3) active @endif"
                                id="domain-setting-tab" data-bs-toggle="pill" data-bs-target="#domain-setting"
                                type="button">{{ __('Staff') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 4) active @endif"
                                id="block-setting-tab" data-bs-toggle="pill" data-bs-target="#block-setting"
                                type="button">{{ __('Business Hours') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 6) active @endif"
                                id="holiday-setting-tab" data-bs-toggle="pill" data-bs-target="#holiday-setting"
                                type="button">{{ __('Business Holiday') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 7) active @endif"
                                id="custom-setting-tab" data-bs-toggle="pill" data-bs-target="#custom-setting"
                                type="button">{{ __('Custom Domain') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 8) active @endif"
                                id="capacity-setting-tab" data-bs-toggle="pill" data-bs-target="#capacity-setting"
                                type="button">{{ __('Appointment') }}</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if (session('tab') and session('tab') == 10) active @endif"
                                id="files-setting-tab" data-bs-toggle="pill" data-bs-target="#files-setting"
                                type="button">{{ __('Custom Field') }}</button>
                        </li>
                        {{-- PWA li  --}}
                        @stack('PWA_menu')
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- [ sample-page ] start -->
        <div class="col-sm-12">
            <div class="row">
                <div class="col-lg-12">
                    <div class="tab-content" id="pills-tabContent">
                        <div class="tab-pane fade @if (!session('tab') or session('tab') and session('tab') == 12) active show @endif"
                            id="theme-setting" role="tabpanel" aria-labelledby="pills-user-tab-12">
                            {{ Form::open(['route' => ['business.theme.update', 'business_id' => $business->id], 'enctype' => 'multipart/form-data']) }}
                            @csrf
                            <div class="card business-card">
                                <div class="business-card-body">
                                    <div class="mb-4 select-theme-portion">
                                        <h4 class="mb-3">{{ __('Select Theme:') }}</h4>
                                        <ul class="gap-2 nav business-tab" id="pills-tab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <div class="nav-item-inner @if ($business->form_type == 'form-layout') active @endif"
                                                    id="theme-setting-tab2" data-bs-toggle="pill"
                                                    data-bs-target="#theme-setting2">
                                                    <label for="radio1">
                                                        <input type="radio" id="radio1" name="form_type"
                                                            value="form-layout">
                                                        <span>
                                                            {{ __('Form Layout') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </li>
                                            <li class="nav-item " role="presentation">
                                                <div id="seo-setting-tab2"
                                                    class="nav-item-inner @if ($business->form_type == 'website') active @endif"
                                                    data-bs-toggle="pill" data-bs-target="#seo-setting2">
                                                    <label for="radio2">
                                                        <input type="radio" id="radio2" name="form_type"
                                                            value="website">
                                                        <span>
                                                            {{ __('Website') }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="tab-content" id="pills-tabContent">
                                        <div class="tab-pane fade @if ($business->form_type == 'form-layout') active show @endif"
                                            id="theme-setting2" role="tabpanel" aria-labelledby="pills-user-tab-1">
                                            <div class="row row-gaps business-card-wrp">
                                                {{ Form::hidden('layouts', null, ['id' => 'themefile1']) }}
                                                @foreach (\App\Models\Business::forms() as $key => $v)
                                                    @php
                                                        $form_name = str_replace('Formlayout', 'Form Layout ', $key);
                                                        if (preg_match('/\d+$/', $form_name, $matches)) {
                                                            $form_name = str_replace(
                                                                $matches[0],
                                                                ' ' . $matches[0],
                                                                $form_name,
                                                            );
                                                        }
                                                    @endphp
                                                    <div class="col-xxl-3 col-lg-4 col-md-6 col-sm-6 col-12 business-view-card d-flex">
                                                        <label for="{{ $key }}">
                                                            <input type="radio" id="{{ $key }}"
                                                                required="" value="{{ $key }}"
                                                                checked="">

                                                            <div class="business-view-inner d-flex flex-column mb-0 h-100">
                                                                <div class="buisness-img mb-3">
                                                                    <img class="color_theme1 {{ $key }}_img"
                                                                        data-id="{{ $key }}"
                                                                        src="{{ asset(get_file('form_layouts/' . $key . '/images/form.png')) }}"
                                                                        alt="" style="height: 100%;width: 100%;">
                                                                </div>
                                                                <div class=" ">
                                                                    <h6 class="mb-0 business-card-title">{{ $form_name }}</h6>

                                                                    <div class="d-flex flex-wrap align-items-center business-color-input mt-1 justify-content-center"
                                                                        id="{{ $key }}">
                                                                        @foreach ($v as $css => $val)
                                                                            <label class="colorinput">
                                                                                <input type="radio" name="theme_color"
                                                                                    id="{{ $css }}"
                                                                                    value="{{ $css }}"
                                                                                    data-theme="{{ $key }}"
                                                                                    data-imgpath="{{ $val['img_path'] }}"
                                                                                    class="colorinput-input"
                                                                                    @if ($css == $business->theme_color) checked @endif>
                                                                                <span class="border-box">
                                                                                    <span class="colorinput-color"
                                                                                        style="background:{{ $val['color'] }}"></span>
                                                                                </span>
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="tab-pane fade @if ($business->form_type == 'website') active show @endif"
                                            id="seo-setting2" role="tabpanel" aria-labelledby="pills-user-tab-1">
                                            <div class="mt-4 row row-gaps business-theme-wrp">
                                                @stack('theme_card')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="flex-wrap gap-2 p-0 mt-4 pt-4 card-footer d-flex justify-content-lg-end justify-content-center">
                                    <input class="btn btn-print-invoice btn-primary m-0" type="submit"
                                        value="Save Changes">
                                </div>
                            </div>
                            {{ Form::close() }}
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 1) show active @endif"
                            id="location-setting" role="tabpanel" aria-labelledby="pills-user-tab-1">

                            <div class="my-3 mt-0 gap-2 header-btn-wrp d-flex justify-content-end">
                                @if (false)
                                    @permission('user import')
                                        @include('import-export::import.button',['module'=>'locations'])
                                    @endpermission
                                    @permission('user export')
                                        @include('import-export::export.button',['module'=>'locations'])
                                    @endpermission
                                @endif
                                @permission('location create')
                                <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                                    data-title="{{ __('Create New Location') }}"
                                    data-url="{{ route('location.create', ['business_id' => $business->id]) }}"
                                    data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
                                    <i class="ti ti-plus"></i>
                                </a>
                            @endpermission
                            </div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-body table-border-style">
                                            <div class="table-responsive">
                                                <table class="table mb-0 pc-dt-simple" id="datatable1">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Name') }}</th>
                                                            <th>{{ __('Phone') }}</th>
                                                            <th>{{ __('Address') }}</th>
                                                            @if (Laratrust::hasPermission('location edit') || Laratrust::hasPermission('location delete'))
                                                                <th>{{ __('Action') }}</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    @foreach ($locations as $location)
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="{{ check_file($location->image) ? get_file($location->image) : get_file('uploads/default/avatar.png') }}"
                                                                            class="img-fluid rounded border-2 border border-primary me-3"
                                                                            width="45" style="height: 45px; min-width:45px">
                                                                        <p class="mb-0">{{ $location->name }}</p>
                                                                    </div>
                                                                </td>
                                                                <td>{{ $location->phone }}</td>
                                                                <td>{{ $location->address }}</td>
                                                                <td>
                                                                    <div class="d-flex">
                                                                        @permission('location edit')
                                                                            <div class="action-btn me-2">
                                                                                <a href="#"
                                                                                    class="btn btn-sm  bg-info align-items-center"
                                                                                    data-url="{{ route('location.edit', $location->id) }}"
                                                                                    class="dropdown-item"
                                                                                    data-ajax-popup="true"
                                                                                    data-title="{{ __('Edit Location') }}"
                                                                                    data-bs-toggle="tooltip"
                                                                                    data-bs-original-title="{{ __('Edit') }}">
                                                                                    <span class="text-white"> <i
                                                                                            class="ti ti-pencil"></i></span></a>
                                                                            </div>
                                                                        @endpermission
                                                                        @permission('location delete')
                                                                            <div class="action-btn">
                                                                                {{ Form::open(['route' => ['location.destroy', $location->id], 'class' => 'm-0']) }}
                                                                                @method('DELETE')
                                                                                <a href="#"
                                                                                    class="btn btn-sm bg-danger align-items-center bs-pass-para show_confirm"
                                                                                    data-bs-toggle="tooltip" title=""
                                                                                    data-bs-original-title="Delete"
                                                                                    aria-label="Delete"
                                                                                    data-confirm-yes="delete-form-{{ $location->id }}"><i
                                                                                        class="text-white ti ti-trash"></i></a>
                                                                                {{ Form::close() }}
                                                                            </div>
                                                                        @endpermission
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    @endforeach
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 2) show active @endif"
                            id="details-setting" role="tabpanel" aria-labelledby="pills-user-tab-2">
                            <div class="my-3 mt-0 gap-2 header-btn-wrp d-flex justify-content-end">
                                @if (false)
                                    @permission('service import')
                                        @include('import-export::import.button', ['module' => 'service'])
                                    @endpermission
                                    @permission('service export')
                                        @include('import-export::export.button', ['module' => 'service'])
                                    @endpermission
                                @endif
                                @permission('service create')
                                    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
                                        data-title="{{ __('Create New Service') }}" data-bs-toggle="tooltip"
                                        data-bs-original-title="{{ __('Create') }}"
                                        data-url="{{ route('service.create', ['business_id' => $business->id]) }}"
                                        id="create_business_data" title="{{ __('Create') }}">
                                        <i class="ti ti-plus"></i>
                                    </a>
                                @endpermission
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card ">
                                        <div class="card-body table-border-style">
                                            <div class="table-responsive">
                                                <table class="table mb-0 pc-dt-simple" id="datatable3">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Name') }}</th>
                                                            <th>{{ __('Category') }}</th>
                                                            <th>{{ __('Price') }}</th>
                                                            <th>{{ __('Duration') }}</th>
                                                            @if (Laratrust::hasPermission('service edit') || Laratrust::hasPermission('service delete'))
                                                                <th>{{ __('Action') }}</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    @foreach ($services as $service)
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="{{ check_file($service->image) ? get_file($service->image) : get_file('uploads/default/avatar.png') }}"
                                                                            class="img-fluid rounded border-2 border border-primary me-3"
                                                                            width="45" style="height: 45px; min-width:45px">
                                                                        <p class="mb-0">{{ $service->name }}</p>
                                                                    </div>
                                                                </td>
                                                                @php
                                                                    $valuesString = '';
                                                                    if (false) {
                                                                        $valuesString = $service->service_id;
                                                                    } elseif (
                                                                        false
                                                                    ) {
                                                                        $valuesString =
                                                                            $service->collaborative_service_id;
                                                                    }
                                                                    $valuesString = trim($valuesString);

                                                                    if (!empty($valuesString)) {
                                                                        $valuesArray = explode(',', $valuesString);
                                                                        $valuesArray = array_map('trim', $valuesArray);
                                                                        $count = count($valuesArray);
                                                                    } else {
                                                                        $count = 0;
                                                                    }
                                                                @endphp
                                                                <td>{{ $service->Category->name }}</td>
                                                                <td>{{ currency_format_with_sym($service->price, $service->created_by, $service->business_id) }}
                                                                </td>
                                                                <td>
                                                                    @if ((false && $service->service_type == 'compound') || (false && $service->service_type == 'collaborative'))
                                                                        {{ $count . ' Services' }}
                                                                    @else
                                                                        {{ $service->duration }}
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex">
                                                                        @if (false)
                                                                            @include(
                                                                                'team-booking::team_booking.service_action',
                                                                                [
                                                                                    'object' => $service,
                                                                                ]
                                                                            )
                                                                        @endif
                                                                        @if (false)
                                                                            @permission('additional service create')
                                                                                @include(
                                                                                    'additional-services::additional_service.service_action',
                                                                                    ['service' => $service]
                                                                                )
                                                                            @endpermission
                                                                        @endif
                                                                        @if (false)
                                                                            @include('easy-deposit-payments::deposit.deposit_details')
                                                                        @endif

                                                                        @if (false)
                                                                            @include('waiting-list::servicebutton.setting')
                                                                        @endif
                                                                        @if (false || false)
                                                                            @include(
                                                                                'online_appointment.online_appointment_btn',
                                                                                [
                                                                                    'serviceData' => $service,
                                                                                    'businessId' => $business->id,
                                                                                ]
                                                                            )
                                                                        @endif
                                                                        @permission('service edit')
                                                                            <div class="action-btn me-2">
                                                                                <a href="#"
                                                                                    class="btn btn-sm   bg-info d-inline align-items-center"
                                                                                    data-url="{{ route('service.edit', $service->id) }}"
                                                                                    class="dropdown-item" data-size="lg"
                                                                                    data-ajax-popup="true"
                                                                                    data-title="{{ __('Edit Service') }}"
                                                                                    data-bs-toggle="tooltip"
                                                                                    data-bs-original-title="{{ __('Edit') }}">
                                                                                    <span class="text-white"> <i
                                                                                            class="ti ti-pencil"></i></span></a>
                                                                            </div>
                                                                        @endpermission
                                                                        @permission('service delete')
                                                                            <div class="action-btn me-2">
                                                                                {{ Form::open(['route' => ['service.destroy', $service->id], 'class' => 'm-0']) }}
                                                                                @method('DELETE')
                                                                                <a href="#"
                                                                                    class="btn btn-sm  bg-danger  align-items-center bs-pass-para show_confirm"
                                                                                    data-bs-toggle="tooltip" title=""
                                                                                    data-bs-original-title="Delete"
                                                                                    aria-label="Delete"
                                                                                    data-confirm-yes="delete-form-{{ $service->id }}"><i
                                                                                        class="text-white ti ti-trash"></i></a>
                                                                                {{ Form::close() }}
                                                                            </div>
                                                                        @endpermission
                                                                        @if (false)
                                                                            @permission('service slot scheduler manage')
                                                                                <div class="action-btn">
                                                                                    <a href="#"
                                                                                        class="btn btn-sm  bg-secondary d-inline align-items-center"
                                                                                        data-url="{{ route('servicedays.create', $service->id) }}"
                                                                                        class="dropdown-item"
                                                                                        data-ajax-popup="true" data-size="xl"
                                                                                        data-title="{{ __('Service Slot Schedule') }}"
                                                                                        data-bs-toggle="tooltip"
                                                                                        data-bs-original-title="{{ __('Service Slot Schedule') }}">
                                                                                        <span class="text-white"> <i
                                                                                                class="text-white ti ti-calendar"></i></span></a>
                                                                                </div>
                                                                            @endpermission
                                                                        @endif
                                                                        </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    @endforeach
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 3) show active @endif"
                            id="domain-setting" role="tabpanel" aria-labelledby="pills-user-tab-3">
                            <div class="my-3 mt-0 gap-2 header-btn-wrp d-flex justify-content-end">
                                @if (false)
                                    @permission('staff import')
                                        @include('import-export::import.button', ['module' => 'staff'])
                                    @endpermission
                                    @permission('staff export')
                                        @include('import-export::export.button', ['module' => 'staff'])
                                    @endpermission
                                @endif
                                @permission('staff create')
                                    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                                        data-title="{{ __('Create New Staff') }}"
                                        data-url="{{ route('staff.create', ['business_id' => $business->id]) }}"
                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
                                        <i class="ti ti-plus"></i>
                                    </a>
                                @endpermission
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-body table-border-style">
                                            <div class="table-responsive">
                                                <table class="table mb-0 pc-dt-simple" id="datatable4">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Name') }}</th>
                                                            <th>{{ __('Email') }}</th>
                                                            <th>{{ __('Location') }}</th>
                                                            <th>{{ __('Service') }}</th>
                                                            @if (false)
                                                                <th>{{ __('Average Rating') }}</th>
                                                            @endif
                                                            @if (Laratrust::hasPermission('staff edit') || Laratrust::hasPermission('staff delete'))
                                                                <th>{{ __('Action') }}</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    @foreach ($staffes as $staff)
                                                        <tbody>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="{{ check_file($staff->user->avatar) ? get_file($staff->user->avatar) : get_file('uploads/default/avatar.png') }}"
                                                                            class="img-fluid rounded border-2 border border-primary me-3"
                                                                            width="45" style="height: 45px; min-width:45px">
                                                                        <p class="mb-0">{{ $staff->name }}</p>
                                                                    </div>
                                                                </td>
                                                                <td>{{ $staff->user->email }}</td>
                                                                <td>{{ $staff->Location()->pluck('name')->implode(', ') }}
                                                                </td>
                                                                <td>{{ $staff->Service()->pluck('name')->implode(', ') }}
                                                                </td>
                                                                @if (false)
                                                                    <td>
                                                                        @if ($staff->review == 0.0)
                                                                            <span class="theme-text-color">-</span>
                                                                        @else
                                                                            @for ($i = 1; $i <= 5; $i++)
                                                                                @if ($staff->review < $i)
                                                                                    @if (is_float($staff->review) && round($staff->review) == $i)
                                                                                        <i
                                                                                            class="text-warning fas fa-star-half-alt"></i>
                                                                                    @else
                                                                                        <i class="fas fa-star"></i>
                                                                                    @endif
                                                                                @else
                                                                                    <i
                                                                                        class="text-warning fas fa-star"></i>
                                                                                @endif
                                                                            @endfor
                                                                            <span
                                                                                class="theme-text-color">({{ number_format($staff->review, 1) }})</span>
                                                                        @endif
                                                                    </td>
                                                                @endif
                                                                <td>
                                                                    <div class="d-flex">
                                                                        @if (false)
                                                                            @include(
                                                                                'team-booking::team_booking.staff_action',
                                                                                [
                                                                                    'object' => $staff,
                                                                                ]
                                                                            )
                                                                        @endif
                                                                        @permission('staff edit')
                                                                            <div class="action-btn  me-2">
                                                                                <a href="#"
                                                                                    class="btn btn-sm bg-info d-inline align-items-center"
                                                                                    data-url="{{ route('staff.edit', $staff->id) }}"
                                                                                    class="dropdown-item"
                                                                                    data-ajax-popup="true"
                                                                                    data-title="{{ __('Edit Staff') }}"
                                                                                    data-bs-toggle="tooltip"
                                                                                    data-bs-original-title="{{ __('Edit') }}">
                                                                                    <span class="text-white"> <i
                                                                                            class="ti ti-pencil"></i></span></a>
                                                                            </div>
                                                                        @endpermission
                                                                        @permission('staff delete')
                                                                            <div class="action-btn me-2">
                                                                                {{ Form::open(['route' => ['staff.destroy', $staff->id], 'class' => 'm-0']) }}
                                                                                @method('DELETE')
                                                                                <a href="#"
                                                                                    class="btn btn-sm  bg-danger align-items-center bs-pass-para show_confirm"
                                                                                    data-bs-toggle="tooltip" title=""
                                                                                    data-bs-original-title="Delete"
                                                                                    aria-label="Delete"
                                                                                    data-confirm-yes="delete-form-{{ $staff->id }}"><i
                                                                                        class="text-white ti ti-trash"></i></a>
                                                                                {{ Form::close() }}
                                                                            </div>
                                                                        @endpermission
                                                                        @if (false)
                                                                            @permission('flexible days manage')
                                                                                <div class="action-btn me-2">
                                                                                    <a href="#"
                                                                                        class="btn btn-sm bg-warning d-inline align-items-center"
                                                                                        data-url="{{ route('flexibledays.create', $staff->user_id) }}"
                                                                                        class="dropdown-item"
                                                                                        data-ajax-popup="true" data-size="xl"
                                                                                        data-title="{{ __('Flexible Days') }}"
                                                                                        data-bs-toggle="tooltip"
                                                                                        data-bs-original-title="{{ __('Flexible Days') }}">
                                                                                        <span class="text-white"> <i
                                                                                                class="text-white ti ti-calendar"></i></span></a>
                                                                                </div>
                                                                            @endpermission
                                                                        @endif
                                                                        @if (false)
                                                                            @permission('flexible hour view')
                                                                                <div class="action-btn">
                                                                                    <a href="#"
                                                                                        class="btn btn-sm  bg-secondary d-inline align-items-center"
                                                                                        data-url="{{ route('flexible.hour.view', $staff->user_id) }}"
                                                                                        class="dropdown-item"
                                                                                        data-ajax-popup="true" data-size="lg"
                                                                                        data-title="{{ __('Flexible Hours') }}"
                                                                                        data-bs-toggle="tooltip"
                                                                                        data-bs-original-title="{{ __('Flexible Hours') }}">
                                                                                        <span class="text-white"> <i
                                                                                                class="text-white ti ti-alarm"></i></span></a>
                                                                                </div>
                                                                            @endpermission
                                                                        @endif
                                                                            </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    @endforeach
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="tab-pane fade @if (session('tab') and session('tab') == 5) show active @endif" id="seo-setting"
                            role="tabpanel" aria-labelledby="pills-user-tab-5">
                            <div class="my-3 mt-0 gap-2 header-btn-wrp d-flex justify-content-end">
                                @if (false)
                                    @permission('category import')
                                        @include('import-export::import.button', ['module' => 'category'])
                                    @endpermission
                                    @permission('category export')
                                        @include('import-export::export.button', ['module' => 'category'])
                                    @endpermission
                                @endif
                                @permission('category create')
                                    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                                        data-title="{{ __('Create New Category') }}"
                                        data-url="{{ route('category.create', ['business_id' => $business->id]) }}"
                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
                                        <i class="ti ti-plus"></i>
                                    </a>
                                @endpermission
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-body table-border-style">
                                            <div class="table-responsive">
                                                <table class="table mb-0 pc-dt-simple" id="datatable2">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('Name') }}</th>
                                                            @if (Laratrust::hasPermission('category edit') || Laratrust::hasPermission('category delete'))
                                                                <th>{{ __('Action') }}</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    @foreach ($categories as $category)
                                                        <tbody>
                                                            <tr>
                                                                <td> {{ $category->name }} </td>
                                                                <td>
                                                                    <div class="d-flex">
                                                                        @permission('category edit')
                                                                            <div class="action-btn me-2">
                                                                                <a href="#"
                                                                                    class="btn btn-sm bg-info  d-inline align-items-center"
                                                                                    data-url="{{ route('category.edit', $category->id) }}"
                                                                                    class="dropdown-item"
                                                                                    data-ajax-popup="true"
                                                                                    data-title="{{ __('Edit Category') }}"
                                                                                    data-bs-toggle="tooltip"
                                                                                    data-bs-original-title="{{ __('Edit') }}">
                                                                                    <span class="text-white"> <i
                                                                                            class="ti ti-pencil"></i></span></a>
                                                                            </div>
                                                                        @endpermission
                                                                        @permission('category delete')
                                                                            <div class="action-btn">
                                                                                {{ Form::open(['route' => ['category.destroy', $category->id], 'class' => 'm-0']) }}
                                                                                @method('DELETE')
                                                                                <a href="#"
                                                                                    class="btn btn-sm bg-danger align-items-center bs-pass-para show_confirm"
                                                                                    data-bs-toggle="tooltip" title=""
                                                                                    data-bs-original-title="Delete"
                                                                                    aria-label="Delete"
                                                                                    data-confirm-yes="delete-form-{{ $category->id }}"><i
                                                                                        class="text-white ti ti-trash"></i></a>
                                                                                {{ Form::close() }}
                                                                            </div>
                                                                        @endpermission
                                                                        </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    @endforeach
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 4) show active @endif"
                            id="block-setting" role="tabpanel" aria-labelledby="pills-user-tab-4">
                            {{ Form::open(['route' => ['business-hours.store', 'business_id' => $business->id], 'enctype' => 'multipart/form-data']) }}
                            <div class="row business-hrs">
                                <div class="col-sm-12">
                                    @php
                                        $days = App\Models\BusinessHours::$weekdays;
                                    @endphp
                                    <div class="card">
                                        @foreach ($days as $index => $day)
                                            <div>
                                                @php
                                                    $data = App\Models\BusinessHours::dayWiseData($day, $business->id);
                                                @endphp
                                                <table class="table mb-0">
                                                    <tbody class="">
                                                        <tr>
                                                            <td>
                                                                <h5 class="mb-0 capitalize text-primary">
                                                                    {{ ++$index . '.' . $day }}</h5>
                                                            </td>
                                                            <td>
                                                                <input type="time" name="{{ $day }}[start]"
                                                                    value="{{ !empty($data['start_time']) ? $data['start_time'] : '' }}"
                                                                    class="form-control day_disable_{{ $index }}"
                                                                    {{ (!empty($data['day_off']) ? $data['day_off'] : '') == 'on' ? 'disabled' : '' }}>
                                                            </td>
                                                            <td>
                                                                <input type="time" name="{{ $day }}[end]"
                                                                    value="{{ !empty($data['end_time']) ? $data['end_time'] : '' }}"
                                                                    class="form-control day_disable_{{ $index }}"
                                                                    {{ (!empty($data['day_off']) ? $data['day_off'] : '') == 'on' ? 'disabled' : '' }}>
                                                            </td>
                                                            <td>
                                                                <div class="form-control">
                                                                    <input class="form-check-input day_off"
                                                                        type="checkbox" data-id={{ $index }}
                                                                        name="{{ $day }}[day_off]"
                                                                        {{ (!empty($data['day_off']) ? $data['day_off'] : '') == 'on' ? 'checked' : '' }}>
                                                                    <label class="form-check-label"
                                                                        for="inlineFormCheck ">
                                                                        {{ __(' Add day off') }}
                                                                    </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            {{-- hours end --}}

                                            {{-- repeater start --}}
                                            <div class="repeater day_off_{{ $index }} {{ (!empty($data['day_off']) ? $data['day_off'] : '') == 'on' ? 'd-none' : '' }}"
                                                id="{{ $day }}_break_business"
                                                data-value="{{ !empty($data['break_hours']) ? $data['break_hours'] : '' }}">
                                                <table class="table mb-0"
                                                    data-repeater-list="{{ $day }}[repeater]">
                                                    <tbody class="" data-repeater-item>
                                                        <tr>
                                                            <td>
                                                                <p class="text-danger">
                                                                    {{ __('Break') }}</p>
                                                            <td>
                                                                <input type="time" name="start"
                                                                    class="form-control repeaterTimeField">
                                                            </td>
                                                            <td>
                                                                <input type="time" name="end"
                                                                    class="form-control repeaterTimeField">
                                                            </td>
                                                            <td>
                                                                <div class="action-btn repeater-action-btn action-btn me-2">
                                                                    <a href="#" class="btn btn-sm bg-danger bs-pass-para repeater-action-btn" data-repeater-delete data-bs-toggle="tooltip" data-bs-placement="top"
                                                                    title="{{ __('Delete') }}">
                                                                        <i class="text-white ti ti-trash"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <div class="mt-2 add-break-btn">
                                                    <span data-repeater-create="">
                                                        <i class="fas fa-plus-circle"></i>
                                                        {{ __('Add break') }}
                                                    </span>
                                                </div>
                                            </div>
                                            {{-- repeater end --}}
                                        @endforeach
                                        <div class="mb-0 form-group col-md-12 text-end card-footer">
                                            <input class="btn btn-print-invoice btn-primary " type="submit"
                                                value="{{ __('Save Changes') }}">
                                        </div>
                                    </div>

                                </div>
                            </div>
                            {{ Form::close() }}
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 6) show active @endif"
                            id="holiday-setting" role="tabpanel" aria-labelledby="pills-user-tab-4">
                            @permission('holiday create')
                                <div class="my-3 mt-0 text-end">
                                    <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                                        data-title="{{ __('Create New Holiday') }}"
                                        data-url="{{ route('business-holiday.create', ['business_id' => $business->id]) }}"
                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
                                        <i class="ti ti-plus"></i>
                                    </a>
                                </div>
                            @endpermission
                            <div class="row business-hrs">
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h5>{{ __('Calendar') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-4">
                                            <div id='calendar' class='calendar tab-cal' data-toggle="calendar"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">{{ __('Holidays') }}</h5>
                                        </div>
                                        <div class="card-body">
                                            <ul class="event-cards list-group list-group-flush w-100">
                                                @foreach ($businessholidays as $businessholiday)
                                                    @php
                                                        $month = date('m', strtotime($businessholiday->date));
                                                    @endphp
                                                    @if ($month == date('m'))
                                                        <li class="mb-3 list-group-item card">
                                                            <div class="row align-items-center justify-content-between">
                                                                <div class="col-auto mb-0">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="theme-avtar badge p-2 px-3 bg-primary">
                                                                            <i class="ti ti-calendar"></i>
                                                                        </div>
                                                                        <div class="ms-3">
                                                                            <h6 class="m-0">
                                                                                <a href="#"
                                                                                    class="fc-daygrid-event sp-inheri">
                                                                                    <div class="fc-event-title-container">
                                                                                        <div
                                                                                            class="fc-event-title text-dark">
                                                                                            {{ $businessholiday->title }}
                                                                                        </div>
                                                                                    </div>
                                                                                </a>
                                                                            </h6>
                                                                            <small
                                                                                class="text-muted">{{ $businessholiday->date }}</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-auto d-flex align-items-center">
                                                                    @permission('holiday edit')
                                                                        <div class="action-btn me-2">
                                                                            <a href="#"
                                                                                class="btn btn-sm   bg-info d-inline align-items-center"
                                                                                data-url="{{ route('business-holiday.edit', $businessholiday->id) }}"
                                                                                class="dropdown-item" data-size="md"
                                                                                data-ajax-popup="true"
                                                                                data-title="{{ __('Edit Holiday') }}"
                                                                                data-bs-toggle="tooltip"
                                                                                data-bs-original-title="{{ __('Edit') }}">
                                                                                <span class="text-white"> <i
                                                                                        class="ti ti-pencil"></i></span></a>
                                                                        </div>
                                                                    @endpermission
                                                                    @permission('holiday delete')
                                                                        <div class="action-btn me-2">
                                                                            {{ Form::open(['route' => ['business-holiday.destroy', $businessholiday->id], 'class' => 'm-0']) }}
                                                                            @method('DELETE')
                                                                            <a href="#"
                                                                                class="btn btn-sm  bg-danger  align-items-center bs-pass-para show_confirm"
                                                                                data-bs-toggle="tooltip" title=""
                                                                                data-bs-original-title="{{__('Delete')}}"
                                                                                aria-label="Delete"
                                                                                data-confirm-yes="delete-form-{{ $businessholiday->id }}"><i
                                                                                    class="text-white ti ti-trash"></i></a>
                                                                            {{ Form::close() }}
                                                                        </div>
                                                                    @endpermission
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

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 7) show active @endif"
                            id="custom-setting" role="tabpanel" aria-labelledby="pills-user-tab-4">
                            <div class="row business-hrs">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h5>{{ __('Custom Domain') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            {{ Form::open(['route' => ['business.domain-setting', $business->id], 'method' => 'POST', 'enctype' => 'multipart/form-data']) }}
                                            <div class="theme-detail-card">
                                                <div class="row gy-3">
                                                    {{-- Start of Custom Domain/Business Link/Sub Domain --}}
                                                    <div class="col-12">
                                                        <div class="row gy-2">
                                                            <div
                                                                class="col-auto {{ isset($company_settings['enable_businesslink']) && $company_settings['enable_businesslink'] == 'on' ? 'active' : '' }}">
                                                                <label for="enable_storelink"
                                                                    class="btn btn-secondary {{ isset($company_settings['enable_businesslink']) && $company_settings['enable_businesslink'] == 'on' ? 'active' : '' }}">
                                                                    <input type="radio"
                                                                        class="btn btn-secondary domain_click d-none"
                                                                        name="enable_domain" value="enable_businesslink"
                                                                        id="enable_storelink"
                                                                        {{ isset($company_settings['enable_businesslink']) && $company_settings['enable_businesslink'] == 'on' ? 'checked' : '' }} /><i
                                                                        class="me-2" data-feather="folder"></i>
                                                                    {{ __('Business Link') }}
                                                                </label>
                                                            </div>

                                                            <div
                                                                class="col-auto {{ isset($company_settings['enable_domain']) && $company_settings['enable_domain'] == 'on' ? 'active' : '' }}">
                                                                <label for="enable_domain"
                                                                    class="btn btn-secondary {{ isset($company_settings['enable_domain']) && $company_settings['enable_domain'] == 'on' ? 'active' : '' }}">
                                                                    <input type="radio"
                                                                        class="domain_click d-none btn btn-secondary"
                                                                        name="enable_domain" value="enable_domain"
                                                                        id="enable_domain"
                                                                        {{ isset($company_settings['enable_domain']) && $company_settings['enable_domain'] == 'on' ? 'checked' : '' }} /><i
                                                                        class="me-2" data-feather="folder"></i>
                                                                    {{ __('Domain') }}
                                                                </label>
                                                            </div>

                                                            <div class="col-auto"
                                                                {{ isset($company_settings['enable_subdomain']) && $company_settings['enable_subdomain'] == 'on' ? 'active' : '' }}>
                                                                <label for="enable_subdomain"
                                                                    class="btn btn-secondary {{ isset($company_settings['enable_subdomain']) && $company_settings['enable_subdomain'] == 'on' ? 'active' : '' }}">
                                                                    <input type="radio"
                                                                        class="domain_click d-none btn btn-secondary"
                                                                        name="enable_domain" value="enable_subdomain"
                                                                        id="enable_subdomain"
                                                                        {{ isset($company_settings['enable_subdomain']) && $company_settings['enable_subdomain'] == 'on' ? 'checked' : '' }}><i
                                                                        class="me-2" data-feather="folder"></i>
                                                                    {{ __('Sub Domain') }}
                                                                </label>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="form-group" id="StoreLink"
                                                            style="{{ isset($company_settings['enable_businesslink']) && $company_settings['enable_businesslink'] == 'on' ? 'display: block' : 'display: none' }}">
                                                            <label class="form-label">{{ __('Business Link:') }}</label>
                                                            <div class="row gy-2">
                                                                <div class="col-xl-11 col-lg-9 col-md-9 col-9">
                                                                    <input type="text"
                                                                        class="form-control d-inline-block" id="myInput"
                                                                        value="{{ $business_url }}" readonly />
                                                                </div>
                                                                <div class="col-xl-1 col-lg-3 col-md-3 col-3">
                                                                    <button type="button" class="btn btn-primary d-flex align-items-center justify-content-center w-100"
                                                                        data-bs-toggle="tooltip"
                                                                        data-bs-placement="top"
                                                                        data-bs-original-title="{{ __('Copy Business Link') }}"
                                                                        title="{{ __('Copy Business Link') }}"
                                                                        id="button-addon2" onclick="myFunction()"><i
                                                                            class="far fa-copy"></i></button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {{-- custom domain view --}}
                                                        <div class="form-group col-md-12 domain"
                                                            style="{{ isset($company_settings['enable_domain']) && $company_settings['enable_domain'] == 'on' ? 'display:block' : 'display:none' }}">
                                                            {{ Form::label('business_domain', __('Custom Domain'), ['class' => 'form-label']) }}
                                                            {{ Form::text('domains', isset($company_settings['domains']) ? $company_settings['domains'] : '', ['class' => 'form-control', 'placeholder' => __('xyz.com')]) }}
                                                        </div>
                                                        @if ($domainPointing == 1)
                                                            <div class="mt-3 text-sm form-group col-md-12" id="domainnote"
                                                                style="display: none">
                                                                <span><b class="text-success">{{ __('Note : Before add Custom Domain, your domain A record is pointing to our server IP :') }}{{ $serverIp }}|
                                                                        {{ __('Your Custom Domain IP Is This: ') }}{{ $domainip }}</b></span>
                                                                <br>
                                                            </div>
                                                        @else
                                                            <div class="mt-3 text-sm form-group col-md-12" id="domainnote"
                                                                style="display: none">
                                                                <span><b>{{ __('Note : Before add Custom Domain, your domain A record is pointing to our server IP :') }}{{ $serverIp }}|</b>
                                                                    <b
                                                                        class="text-danger">{{ __('Your Custom Domain IP Is This: ') }}{{ $domainip }}</b></span>
                                                                <br>
                                                            </div>
                                                        @endif
                                                        {{-- End custom domain --}}

                                                        <div class="form-group col-md-12 sundomain"
                                                            style="{{ isset($company_settings['enable_subdomain']) && $company_settings['enable_subdomain'] == 'on' ? 'display:block' : 'display:none' }}">
                                                            {{ Form::label('business_subdomain', __('Sub Domain'), ['class' => 'form-label']) }}
                                                            <div class="input-group gap-3 flex-wrap">
                                                                {{ Form::text('subdomain', $business->slug, ['class' => 'form-control rounded', 'placeholder' => __('Enter Domain'), 'readonly']) }}
                                                                <div class="input-group-append">
                                                                    <span class="py-1 input-group-text h-44 rounded"
                                                                        id="basic-addon2">.{{ $subdomain_name }}</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        @if ($subdomainPointing == 1)
                                                            <div class="mt-2 text-sm" id="subdomainnote"
                                                                style="display: none">
                                                                <span><b class="text-success">{{ __('Note : Before add Sub Domain, your domain A record is pointing to our server IP :') }}{{ $serverIp }}|
                                                                        {{ __('Your Sub Domain IP Is This: ') }}{{ $domainip }}</b></span>
                                                            </div>
                                                        @else
                                                            <div class="mt-2 text-sm" id="subdomainnote"
                                                                style="display: none">
                                                                <span><b>{{ __('Note : Before add Sub Domain, your domain A record is pointing to our server IP :') }}{{ $serverIp }}|</b>
                                                                    <b
                                                                        class="text-danger">{{ __('Your Sub Domain IP Is This: ') }}{{ $domainip }}</b></span>
                                                            </div>
                                                        @endif

                                                    </div>
                                                    <div class="col-md-12 text-end">
                                                        <input class="btn btn-print-invoice btn-primary " type="submit"
                                                            value="{{ __('Save Changes') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade appointment-tab-content @if (session('tab') and session('tab') == 8) show active @endif"
                            id="capacity-setting" role="tabpanel" aria-labelledby="pills-user-tab-4">
                            <div class="row business-hrs">
                                <div class="col-lg-6 col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h5>{{ __('Appointment Slot Capacity Setting') }}</h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            {{ Form::open(['route' => ['slot.capacity-setting', $business->id], 'method' => 'POST', 'enctype' => 'multipart/form-data']) }}
                                            <div class="theme-detail-card">
                                                <div class="row gy-3">
                                                    <div class="col-12">
                                                        <div class="form-group mb-0" id="StoreLink">
                                                            <label
                                                                class="form-label ">{{ __('Appointment Slot Capacity:') }}</label>
                                                            <div class="">
                                                                <input type="number" class="form-control d-inline-block"
                                                                    id="maximum_slot" name="maximum_slot"
                                                                    value="{{ isset($company_settings['maximum_slot']) ? $company_settings['maximum_slot'] : '' }}"
                                                                    placeholder="Enter maximum appointment slot number"
                                                                    required />
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div class="col-md-12 text-end mt-0">
                                                        <input class="btn btn-print-invoice btn-primary " type="submit"
                                                            value="{{ __('Save Changes') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h5 class="mb-2">{{ __('Appointment Reminder') }}</h5>
                                                    <small>{{ __('To enable automated appointment reminders, you need to set up a cron job on your server. (Command: * * * * * domain && php artisan schedule:run >/dev/null 2>&1)') }}</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            {{ Form::open(['route' => ['appointment.reminder-setting', $business->id], 'method' => 'POST', 'enctype' => 'multipart/form-data']) }}
                                            <div class="theme-detail-card">
                                                <div class="row gy-3">
                                                    <div class="col-12">
                                                        <div class="form-group mb-0" id="StoreLink">
                                                            <label
                                                                class="form-label">{{ __('Reminder Interval (minute) :') }}</label>
                                                            <div class="">
                                                                <input type="number" class="form-control d-inline-block"
                                                                    id="reminder_interval" name="reminder_interval"
                                                                    value="{{ isset($company_settings['reminder_interval']) ? $company_settings['reminder_interval'] : '' }}"
                                                                    placeholder="Enter Reminder Interval in minute"
                                                                    required />
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div class="col-md-12 text-end mt-0">
                                                        <input class="btn btn-print-invoice btn-primary " type="submit"
                                                            value="{{ __('Save Changes') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade @if (session('tab') and session('tab') == 10) show active @endif"
                            id="files-setting" role="tabpanel" aria-labelledby="pills-user-tab-4">
                            {{ Form::open(['route' => ['files.setting', $business->id], 'method' => 'POST', 'enctype' => 'multipart/form-data']) }}
                            @csrf
                            <div class="row business-hrs">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-sm-10 col-9">
                                                    <h5 class="">{{ __('File') }}</h5>
                                                </div>
                                                <div class="col-sm-2 col-3 text-end">
                                                    <div class="form-check form-switch custom-switch-v1 float-end">
                                                        <input type="checkbox" name="file_enable"
                                                            class="form-check-input input-primary" id="file_enable" data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="{{ __('Enable/Disable') }}"
                                                            {{ (isset($files->value) ? $files->value : 'off') == 'on' ? ' checked ' : '' }}>
                                                        <label class="form-check-label" for="file_enable"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-0">
                                                        <label for="file_label"
                                                            class="form-label">{{ __('label') }}</label>
                                                        <input class="form-control files_lbl"
                                                            placeholder="{{ __('Enter label here') }}" name="file_label"
                                                            type="text"
                                                            value="{{ isset($files->label) ? $files->label : '' }}"
                                                            {{ (isset($files->value) ? $files->value : 'off') == 'on' ? '' : ' disabled' }}
                                                            id="file_label">
                                                        <div class="pt-3 choose-files">
                                                            <label for="image">
                                                                <div class=" bg-primary"> <i
                                                                        class="px-1 ti ti-upload"></i>{{ __('Choose file here') }}
                                                                </div>
                                                                <input type="file" class="form-control file"
                                                                    name="image" id="image" data-filename="image"
                                                                    disabled>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer text-end">
                                            <input class="btn btn-print-invoice btn-primary" type="submit"
                                                value="{{ __('Save Changes') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{ Form::close() }}

                            {{ Form::open(['route' => ['custom-field.setting', $business->id], 'method' => 'POST', 'enctype' => 'multipart/form-data']) }}
                            @csrf
                            <div class="row business-hrs">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row">
                                                <div class="col-sm-10 col-9">
                                                    <h5 class="">{{ __('Custom Field') }}</h5>
                                                </div>
                                                <div class="col-sm-2 col-3 text-end">
                                                    <div class="form-check form-switch custom-switch-v1 float-end">
                                                        <input type="checkbox" name="custom_field_enable"
                                                            class="form-check-input input-primary"
                                                            id="custom_field_enable" data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="{{ __('Enable/Disable') }}"
                                                            {{ (isset($custom_field) ? $custom_field : 'off') == 'on' ? ' checked ' : '' }}>
                                                        <label class="form-check-label" for="custom_field_enable"></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mt-2">
                                                <div id="fields-container" class="custom-textarea">
                                                    @foreach ($custom_fields as $custom_field)
                                                        <div class="row align-items-center">
                                                            <div class="col-sm-5 col-12 mb-3">
                                                                <input type="hidden" name="ids[]"
                                                                    value="{{ $custom_field->id }}">
                                                                <input type="hidden" name="customoptions[]"
                                                                    value="{{ $custom_field->option }}">

                                                                @if (
                                                                    $custom_field->type == 'textfield' ||
                                                                        $custom_field->type == 'textarea' ||
                                                                        $custom_field->type == 'date' ||
                                                                        $custom_field->type === 'number')
                                                                    <input type="text"
                                                                        class = "custom_lbl form-control" name="labels[]"
                                                                        placeholder="Label" required
                                                                        value="{{ $custom_field->label }}" required>
                                                                @endif
                                                            </div>
                                                            <div class="col-sm-5 col-12 mb-3">
                                                                @if ($custom_field->type === 'textfield')
                                                                    <input type="text" name="values[]"
                                                                        placeholder="Value"
                                                                        value="{{ $custom_field->value }}"
                                                                        class="custom_lbl form-control" disabled>
                                                                @elseif($custom_field->type === 'textarea')
                                                                    <textarea name="values[]" placeholder="Value" class="custom_lbl form-control" disabled>{{ $custom_field->value }}</textarea>
                                                                @elseif($custom_field->type === 'date')
                                                                    <input type="date" class="form-control custom_lbl"
                                                                        name="values[]"
                                                                        value="{{ $custom_field->value }}" disabled>
                                                                @elseif($custom_field->type === 'number')
                                                                    <input type="number" class="form-control custom_lbl"
                                                                        name="values[]" placeholder="Value"
                                                                        value="{{ $custom_field->value }}" disabled>
                                                                @endif

                                                                <input type="hidden" name="types[]"
                                                                    value="{{ $custom_field->type }}">

                                                            </div>

                                                            @if (
                                                                $custom_field->type == 'textfield' ||
                                                                    $custom_field->type == 'textarea' ||
                                                                    $custom_field->type == 'date' ||
                                                                    $custom_field->type === 'number')
                                                           <div
                                                           class="col-sm-2 col-12 text-center text-sm-start mb-3">
                                                           <div class="action-btn">
                                                               <a href="#" class="btn btn-sm bg-danger btn-delete  align-items-center" data-id="{{ $custom_field->id }}" data-bs-toggle="tooltip" data-bs-placement="top"
                                                                title="{{ __('Delete') }}"><i class="ti ti-trash text-white text-white"></i></a>
                                                           </div>
                                                       </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    @stack('additional_custom_field')
                                                    <!-- Fields will be dynamically added here -->
                                                </div>

                                            </div>
                                        </div>
                                        <div
                                            class="card-footer  d-flex flex-wrap justify-content-lg-end justify-content-center gap-2">
                                            <button type="button"
                                                class="btn btn-print-invoice  btn-secondary me-0 m-r-10 btn-add-textfield">{{ __('Add TextField') }}</button>
                                            <button type="button"
                                                class="btn btn-print-invoice  btn-secondary me-0 m-r-10 btn-add-textarea">{{ __('Add TextArea') }}</button>
                                            <button type="button"
                                                class="btn btn-print-invoice  btn-secondary me-0 m-r-10 btn-add-date">{{ __('Add Date') }}</button>
                                            <button type="button"
                                                class="btn btn-print-invoice  btn-secondary me-0 m-r-10 btn-add-number">{{ __('Add Number') }}</button>
                                            @stack('additional_custom_button')
                                            <input class="btn btn-print-invoice  btn-primary me-0 m-r-10" type="submit"
                                                value="{{ __('Save Changes') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{ Form::close() }}
                        </div>

                        {{-- PWA Tab  --}}
                        @stack('PWA_menu_tab')

                    </div>
                </div>
            </div>
        </div>
        <!-- [ sample-page ] end -->
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('/assets/js/calendar.js') }}"></script>
    <script>
        function previewImage(input) {
            const image = document.getElementById('blah');
            if (input.files && input.files[0]) {
                image.src = URL.createObjectURL(input.files[0]);
                image.style.display = 'block';
            } else {
                image.style.display = 'none';
            }
        }
    </script>
    <script>
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
            $('input[name="labels[]"]').attr('required', true);
            $('.btn-add-textfield').click(function() {
                $('#fields-container').append(
                    '<div class="row align-items-center"><div class="col-sm-5 col-12 mb-3"><input type="text" class="form-control" name="labels[]" placeholder="Label" required></div> <div class="col-sm-5 col-12 mb-3"><input type="text" class="form-control" name="values[]" placeholder="Value" disabled><input type="hidden" name="types[]" value="textfield"></div><div class="col-sm-2 col-12 text-center text-sm-start mb-3"><div class="action-btn"><a href="#" class="btn btn-sm bg-danger btn-delete  align-items-center"><i class="ti ti-trash text-white text-white"></i></a></div></div></div>'
                );
            });

            $('.btn-add-textarea').click(function() {
                $('#fields-container').append(
                    '<div class="row align-items-center"> <div class="col-sm-5 col-12 mb-3"><input type="text" class="form-control" name="labels[]" placeholder="Label" required></div> <div class="col-sm-5 col-12 mb-3"><textarea name="values[]" class="form-control" placeholder="Value" disabled></textarea><input type="hidden" name="types[]" value="textarea"></div><div class="col-sm-2 col-12 text-center text-sm-start mb-3"><div class="action-btn"><a href="#" class="btn btn-sm bg-danger btn-delete  align-items-center"><i class="ti ti-trash text-white text-white"></i></a></div></div></div>'
                );
            });

            $('.btn-add-date').click(function() {
                $('#fields-container').append(
                    '<div class="row align-items-center"><div class="col-sm-5 col-12 mb-3"><input type="text" class="form-control" name="labels[]" placeholder="Label" required></div><div class="col-sm-5 col-12 mb-3"><input type="date" class="form-control" name="values[]" value="{{ date('Y-m-d') }}" disabled><input type="hidden" name="types[]" value="date"></div><div class="col-sm-2 col-12 text-center text-sm-start mb-3"><div class="action-btn"><a href="#" class="btn btn-sm bg-danger btn-delete  align-items-center"><i class="ti ti-trash text-white text-white"></i></a></div></div></div>'
                );
            });

            $('.btn-add-number').click(function() {
                $('#fields-container').append(
                    '<div class="row align-items-center"><div class="col-sm-5 col-12 mb-3"><input type="text" class="form-control" name="labels[]" placeholder="Label" required></div><div class="col-sm-5 col-12 mb-3"><input type="number" class="form-control" name="values[]" placeholder="Value" disabled><input type="hidden" name="types[]" value="number"></div><div class="col-sm-2 col-12 text-center text-sm-start mb-3"><div class="action-btn"><a href="#" class="btn btn-sm bg-danger btn-delete  align-items-center"><i class="ti ti-trash text-white text-white"></i></a></div></div></div>'
                );
            });

            $(document).on('click', '.btn-delete', function() {
                var customFieldId = $(this).data('id');
                var $row = $(this).closest('.row');

                if (customFieldId) {
                    // AJAX call to delete the field
                    $.ajax({
                        url: '{{ route('delete.field') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            id: customFieldId
                        },
                        success: function(response) {
                            if (response.success) {
                                $row.remove();
                                toastrs('Success', response.message, 'success');
                            } else {
                                toastrs('Error', response.message, 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            toastrs('Error', 'Failed to delete the field.', 'error');
                        }
                    });
                } else {
                    // If customFieldId is not available, just remove the row
                    $row.remove();
                }
            });
        });
    </script>

    @php
        $weekFirstDay = isset($company_settings['week_start_day']) ? $company_settings['week_start_day'] : '0';
    @endphp
    <script type="text/javascript">
        "use strict";
        (function() {
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
                firstDay: {{ $weekFirstDay }},
                events: {!! json_encode($businessholidays) !!},
            });
            calendar.render();
        })();

        $(document).on('click', '#file_enable', function() {
            if ($('#file_enable').prop('checked')) {
                $(".files_lbl").removeAttr("disabled");
            } else {
                $('.files_lbl').attr("disabled", "disabled");
            }
        });

        // $(document).on('click', '#custom_field_enable', function() {
        //     if ($('#custom_field_enable').prop('checked')) {
        //         $(".custom_lbl").removeAttr("disabled");
        //     } else {
        //         $('.custom_lbl').attr("disabled", "disabled");
        //     }
        // });

        var selector = "body";
        var slides = document.getElementsByClassName("repeater");

        const days = ["monday_break_business", "tuesday_break_business", "wednesday_break_business",
            "thursday_break_business", "friday_break_business", "saturday_break_business", "sunday_break_business"
        ];
        for (var i = 0; i < slides.length; i++) {
            var repeaterDayId = '#' + days[i];
            var $dragAndDrop = $("body .repeater tbody").sortable({
                handle: '.sort-handler'
            });
            var $repeater = $(repeaterDayId).repeater({
                initEmpty: true,

                hide: function(deleteElement) {
                    $(this).remove();
                },
                ready: function(setIndexes) {
                    $dragAndDrop.on('drop', setIndexes);
                },

                isFirstItemUndeletable: false
            });
            var value = $(repeaterDayId).attr('data-value');
            if (typeof value != 'undefined' && value.length != 0) {
                value = JSON.parse(value);
                $repeater.setList(value);
            }
            $(document).on('click', '[data-repeater-create]', function() {
                $(document).on('click', '.repeaterTimeField', function() {
                    $(this).attr('type', 'time');
                });
            });

        }


        $(document).on('click', '.day_off', function() {
            var data_id = '.day_off_' + $(this).data('id');
            var id = '.day_disable_' + $(this).data('id');
            let isChecked = $(this).is(':checked')

            if (isChecked) {
                $(data_id).addClass("d-none");
                $(id).prop('disabled', true);

            } else {
                $(data_id).removeClass("d-none");
                $(id).prop('disabled', false);
            }
        });

        $(document).on('click', '.repeaterTimeField', function() {
            $(this).attr('type', 'time');
        });

        $(document).ready(function() {
            var checked = $("input[type=radio][name='enable_domain']:checked");
            $(checked).closest('#enable_storelink').removeClass('btn-primary');
            $(checked).parent().addClass('btn-primary');
        });

        $(document).on('change', '.domain_click#enable_storelink', function(e) {

            $('#StoreLink').show();
            $('.sundomain').hide();
            $('.domain').hide();
            $('#domainnote').hide();
            $(this).parent().removeClass('btn-secondary');
            $(this).parent().addClass('btn-primary');
            $('#enable_domain').parent().addClass('btn-secondary');
            $('#enable_domain').parent().removeClass('btn-primary');
            $('#enable_subdomain').parent().addClass('btn-secondary');
            $('#enable_subdomain').parent().removeClass('btn-primary');
        });
        $(document).on('change', '.domain_click#enable_domain', function(e) {
            $('.domain').show();
            $('#StoreLink').hide();
            $('.sundomain').hide();
            $('#domainnote').show();
            $(this).parent().removeClass('btn-secondary');
            $(this).parent().addClass('btn-primary');
            $('#enable_storelink').parent().addClass('btn-secondary');
            $('#enable_storelink').parent().removeClass('btn-primary');
            $('#enable_subdomain').parent().addClass('btn-secondary');
            $('#enable_subdomain').parent().removeClass('btn-primary');

        });
        $(document).on('change', '.domain_click#enable_subdomain', function(e) {
            $('.sundomain').show();
            $('#StoreLink').hide();
            $('.domain').hide();
            $('#domainnote').hide();
            $(this).parent().removeClass('btn-secondary');
            $(this).parent().addClass('btn-primary');
            $('#enable_storelink').parent().addClass('btn-secondary');
            $('#enable_storelink').parent().removeClass('btn-primary');
            $('#enable_domain').parent().addClass('btn-secondary');
            $('#enable_domain').parent().removeClass('btn-primary');
        });

        function myFunction() {
            var copyText = document.getElementById("myInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999)
            document.execCommand("copy");
        }

        $(document).on('click', 'input[name="theme_color"]', function() {
            var eleParent = $(this).attr('data-theme');
            $('#themefile1').val(eleParent);
            var imgpath = $(this).attr('data-imgpath');
            $('.' + eleParent + '_img').attr('src', imgpath);

            $('.form_preview_img').attr('src', imgpath);
            var imgpath = $(this).attr('data-imgpath');
            $(".business-view-card").removeClass('selected-theme')
            $(this).closest('.business-view-card').addClass('selected-theme');
        });

        $(document).on("click", ".color_theme1", function() {
            var id = $(this).attr('data-id');
            $(".business-view-card").removeClass('selected-theme')
            $(this).closest('.business-view-card').addClass('selected-theme');

            var dataId = $(this).attr("data-id");
            $('#color1-' + dataId).trigger('click');
            $(".business-view-card").addClass('')
        });

        $(document).ready(function() {
            var checked = $("input[type=radio][name='theme_color']:checked");
            $('#themefile1').val(checked.attr('data-theme'));
            $(checked).closest('.business-view-card').addClass('selected-theme');
        });

        $(document).ready(function() {
            setTimeout(function(e) {
                var checked = $("input[type=radio][name='theme_color']:checked");
                $('#themefile').val(checked.attr('data-theme'));
                $('.' + checked.attr('data-theme') + '_img').attr('src', checked.attr('data-imgpath'));
            }, 100);
        });
    </script>
@endpush

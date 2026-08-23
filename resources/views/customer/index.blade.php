@extends('layouts.main')

@section('page-title')
    {{ __('Customers') }}
@endsection
@section('page-breadcrumb')
    {{ __('Customers') }}
@endsection
@section('page-action')
    <div class="d-flex gap-2">
        @if (false)
            @permission('customer import')
                @include('import-export::import.button', ['module' => 'customers'])
            @endpermission
            @permission('customer export')
                @include('import-export::export.button', ['module' => 'customers'])
            @endpermission
        @endif
        @permission('customer manage')
            <a href="{{ route('customer.list') }}" class="btn btn-sm btn-primary btn-icon "
                data-bs-toggle="tooltip" title="{{ __('List View') }}" style="">
                <i class="ti ti-list text-white"></i>
            </a>
        @endpermission
        @permission('customer create')
            <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                data-title="{{ __('Create New Customer') }}" data-url="{{ route('customer.create') }}" data-bs-toggle="tooltip"
                data-bs-original-title="{{ __('Create') }}">
                <i class="ti ti-plus"></i>
            </a>
        @endpermission
    </div>
@endsection
@section('content')
    <!-- [ Main Content ] start -->
    <div class="row ">
    <div class="mb-4">
                <div class="card mb-0">
                    <div class="card-body user-card-head header-btn-wrp">
                        {{ Form::open(['route' => ['customer.index'], 'method' => 'GET', 'id' => 'user_submit']) }}
                        <div class="row d-flex align-items-center justify-content-end">
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                                <div class="btn-box">
                                    {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}
                                    {{ Form::text('name', isset($_GET['name']) ? $_GET['name'] : null, ['class' => 'form-control', 'placeholder' => 'Enter Name']) }}
                                </div>
                            </div>
                            <div class="col-xl-2 col-lg-3 col-md-6 col-sm-12 col-12 mr-2">
                                <div class="btn-box">
                                    {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
                                    {{ Form::text('email', isset($_GET['email']) ? $_GET['email'] : null, ['class' => 'form-control', 'placeholder' => 'Enter Email']) }}
                                </div>
                            </div>
                            <div class="col-auto float-end mt-4 d-flex">
                                <a href="#" class="btn btn-sm btn-primary me-2"
                                    onclick="document.getElementById('user_submit').submit(); return false;"
                                    data-bs-toggle="tooltip" title="{{ __('Apply') }}"
                                    data-original-title="{{ __('apply') }}">
                                    <span class="btn-inner--icon"><i class="ti ti-search"></i></span>
                                </a>
                                <a href="{{ route('customer.index') }}" id="clearfilter" class="btn btn-sm btn-danger"
                                    data-bs-toggle="tooltip" title="{{ __('Reset') }}"
                                    data-original-title="{{ __('Reset') }}">
                                    <span class="btn-inner--icon"><i class="ti ti-refresh"></i></span>
                                </a>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
        @foreach ($customers as $customer)
            <div class="col-xxl-3 col-xl-4 col-md-6 mb-4">
                <div class="card user-card">
                    <div class="card-header border border-bottom p-3">
                        <div class="user-img-wrp d-flex align-items-center">
                            <div class="user-image rounded border-2 border border-primary">
                                <a href="{{ isset($customer->customer) && $customer->customer->avatar ? get_file($customer->customer->avatar) : get_file('uploads/users-avatar/avatar.png') }}"
                                    target="_blank">
                                    <img src="{{ isset($customer->customer) && $customer->customer->avatar ? get_file($customer->customer->avatar) : get_file('uploads/users-avatar/avatar.png') }}"
                                        alt="user-image" class=" h-100 w-100">
                                </a>
                            </div>
                            <div class="user-content">
                                <h4 class="mb-2">{{ !empty($customer->name) ? $customer->name : '' }}</h4>
                                <span class="text-dark text-md">{{ $customer->customer->email ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center p-3">
                        <div class="bottom-icons d-flex flex-wrap align-items-center justify-content-between">
                            <div class="edit-btn-wrp d-flex flex-wrap align-items-center">
                                    @if (Laratrust::hasPermission('customer edit') || Laratrust::hasPermission('customer delete'))
                                        @permission('customer edit')
                                            <a href="#!" data-url="{{ route('customer.edit', $customer['id']) }}"
                                                data-ajax-popup="true" data-size="md" data-title="{{ __('Edit') }}"
                                                data-bs-original-title="{{ __('Edit') }}" data-bs-toggle="tooltip"
                                                data-bs-placement="top" class="btn btn-sm border">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    viewBox="0 0 16 16" fill="none">
                                                    <path
                                                        d="M1.56382 11.5713C1.40611 11.5713 1.24871 11.5112 1.12827 11.3908C0.887704 11.1502 0.887704 10.7603 1.12827 10.5197L10.7553 0.892668C10.9956 0.6521 11.3858 0.6521 11.6264 0.892668C11.867 1.13324 11.867 1.5232 11.6264 1.76376L1.99937 11.3908C1.87924 11.5109 1.72153 11.5713 1.56382 11.5713Z"
                                                        fill="#060606" />
                                                    <path
                                                        d="M0.61263 16.0078C0.569815 16.0078 0.526383 16.0031 0.482952 15.9939C0.150284 15.9224 -0.0616371 15.595 0.00982476 15.2623L0.961623 10.8258C1.03308 10.4932 1.36206 10.2819 1.69318 10.3527C2.02585 10.4242 2.23777 10.7516 2.16631 11.0843L1.21451 15.5208C1.1526 15.81 0.896938 16.0078 0.61263 16.0078Z"
                                                        fill="#060606" />
                                                    <path
                                                        d="M5.04863 15.056C4.89092 15.056 4.73352 14.9959 4.61308 14.8755C4.37251 14.6349 4.37251 14.245 4.61308 14.0044L14.2401 4.37767C14.4804 4.1371 14.8706 4.1371 15.1112 4.37767C15.3518 4.61824 15.3518 5.0082 15.1112 5.24877L5.48448 14.8755C5.36404 14.9959 5.20633 15.056 5.04863 15.056Z"
                                                        fill="#060606" />
                                                    <path
                                                        d="M0.611348 16.0078C0.32704 16.0078 0.0716875 15.81 0.0094664 15.5208C-0.0616875 15.1881 0.149926 14.8607 0.482593 14.7892L4.91908 13.8374C5.25206 13.7669 5.57949 13.9782 5.65064 14.3105C5.7218 14.6432 5.51018 14.9706 5.17752 15.0421L0.741027 15.9939C0.697595 16.0034 0.654163 16.0078 0.611348 16.0078Z"
                                                        fill="#060606" />
                                                    <path
                                                        d="M12.9331 7.17142C12.7754 7.17142 12.6177 7.11136 12.4976 6.99092L9.01287 3.50623C8.7723 3.26566 8.7723 2.8757 9.01287 2.63514C9.25313 2.39457 9.6437 2.39457 9.88396 2.63514L13.3687 6.11983C13.6092 6.36039 13.6092 6.75035 13.3687 6.99092C13.2485 7.11136 13.0908 7.17142 12.9331 7.17142Z"
                                                        fill="#060606" />
                                                    <path
                                                        d="M14.6757 5.42925C14.518 5.42925 14.3603 5.36919 14.2399 5.24875C13.9993 5.00818 13.9993 4.61822 14.2399 4.37735C14.5827 4.03452 14.7715 3.57032 14.7715 3.0707C14.7715 2.57109 14.5827 2.10689 14.2399 1.76406C13.8967 1.42092 13.4325 1.2321 12.9329 1.2321C12.4333 1.2321 11.9691 1.42092 11.6263 1.76406C11.386 2.00463 10.996 2.00494 10.7549 1.76406C10.5143 1.52349 10.5143 1.13353 10.7549 0.892657C11.3303 0.316958 12.1037 0 12.9329 0C13.7618 0 14.5356 0.316958 15.111 0.892657C15.6867 1.46805 16.0036 2.2415 16.0036 3.0707C16.0036 3.89991 15.6867 4.67336 15.111 5.24875C14.9911 5.36888 14.8334 5.42925 14.6757 5.42925Z"
                                                        fill="#060606" />
                                                </svg>
                                            </a>
                                        @endpermission
                                        @permission('customer delete')
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'route' => ['customer.destroy', $customer['id']],
                                                'style' => 'display:inline;',
                                            ]) !!}
                                            <a href="#!" aria-label="Delete" data-confirm="{{ __('Are You Sure?') }}"
                                                data-text="{{ __('This action cannot be undone. Do you want to continue?') }}"
                                                data-confirm-yes="delete-form-{{ $customer->id }}"
                                                data-title="{{ __('Delete') }}" data-bs-original-title="{{ __('Delete') }}"
                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                class="btn btn-sm border show_confirm">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    viewBox="0 0 16 16" fill="none">
                                                    <g clip-path="url(#clip0_11_8426)">
                                                        <path
                                                            d="M13.625 1.875H11.2812V1.40625C11.2812 0.630844 10.6504 0 9.875 0H6.125C5.34959 0 4.71875 0.630844 4.71875 1.40625V1.875H2.375C1.59959 1.875 0.96875 2.50584 0.96875 3.28125C0.96875 3.904 1.37578 4.43316 1.93766 4.61753L2.77375 14.7105C2.83397 15.4336 3.44953 16 4.17513 16H11.8249C12.5505 16 13.1661 15.4336 13.2263 14.7103L14.0623 4.6175C14.6242 4.43316 15.0312 3.904 15.0312 3.28125C15.0312 2.50584 14.4004 1.875 13.625 1.875ZM5.65625 1.40625C5.65625 1.14778 5.86653 0.9375 6.125 0.9375H9.875C10.1335 0.9375 10.3438 1.14778 10.3438 1.40625V1.875H5.65625V1.40625ZM12.292 14.6327C12.2719 14.8737 12.0667 15.0625 11.8249 15.0625H4.17513C3.93328 15.0625 3.72809 14.8737 3.70806 14.6329L2.88419 4.6875H13.1158L12.292 14.6327ZM13.625 3.75H2.375C2.11653 3.75 1.90625 3.53972 1.90625 3.28125C1.90625 3.02278 2.11653 2.8125 2.375 2.8125H13.625C13.8835 2.8125 14.0938 3.02278 14.0938 3.28125C14.0938 3.53972 13.8835 3.75 13.625 3.75Z"
                                                            fill="#060606" />
                                                        <path
                                                            d="M6.12409 13.6272L5.65534 6.06472C5.63931 5.80631 5.41566 5.60978 5.1585 5.62588C4.90009 5.64191 4.70363 5.86435 4.71963 6.12272L5.18838 13.6853C5.20378 13.9338 5.41016 14.125 5.65578 14.125C5.92725 14.125 6.14075 13.8964 6.12409 13.6272Z"
                                                            fill="#060606" />
                                                        <path
                                                            d="M8 5.625C7.74112 5.625 7.53125 5.83487 7.53125 6.09375V13.6562C7.53125 13.9151 7.74112 14.125 8 14.125C8.25888 14.125 8.46875 13.9151 8.46875 13.6562V6.09375C8.46875 5.83487 8.25888 5.625 8 5.625Z"
                                                            fill="#060606" />
                                                        <path
                                                            d="M10.8415 5.62591C10.5837 5.60987 10.3606 5.80634 10.3446 6.06475L9.87587 13.6272C9.85991 13.8856 10.0564 14.1081 10.3147 14.1241C10.5733 14.1401 10.7956 13.9435 10.8116 13.6852L11.2803 6.12275C11.2963 5.86434 11.0999 5.64191 10.8415 5.62591Z"
                                                            fill="#060606" />
                                                    </g>
                                                    <defs>
                                                        <clipPath id="clip0_11_8426">
                                                            <rect width="16" height="16" fill="white" />
                                                        </clipPath>
                                                    </defs>
                                                </svg>
                                            </a>
                                            {!! Form::close() !!}
                                        @endpermission
                                    @endif
                            </div>
                            <span class="badge bg-primary p-2 px-3">
                                    <span class="text-white">
                                        {{ __('Customer') }}
                                    </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @auth('web')
            @permission('customer create')
                <div class="col-xxl-3 col-xl-4 col-md-6">
                    <a href="#" class="btn-addnew-project border-primary p-4" data-ajax-popup="true" data-size="md"
                        data-title="{{ __('Create New Customer') }}" data-url="{{ route('customer.create') }}" >
                        <div class="bg-primary proj-add-icon" data-bs-placement="top" data-bs-toggle="tooltip"
                        data-bs-original-title="{{ __('Create') }}">
                            <i class="ti ti-plus my-2"></i>
                        </div>
                        <h6 class="mt-4 mb-2">{{ __('New Customer') }}</h6>
                        <p class="text-muted text-center mb-0">{{ __('Click here to Create New Customer') }}</p>
                    </a>
                </div>
            @endpermission
        @endauth
    </div>
    {!! $customers->links('vendor.pagination.global-pagination') !!}
    <!-- [ Main Content ] end -->
@endsection
@push('scripts')
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
@endpush

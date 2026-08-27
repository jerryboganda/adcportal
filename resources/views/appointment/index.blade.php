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
@endpush
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="mt-2" id="multiCollapseExample1">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12">
                                <div class="btn-box">
                                    {!! Form::label('date', __('Date'), ['class' => 'form-label']) !!}
                                    {!! Form::date('date', $date ?? null, ['class' => 'form-control', 'required' => true]) !!}
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-6 col-12">
                                <div class="btn-box">
                                    {!! Form::label('service', __('Service'), ['class' => 'form-label']) !!}
                                    {!! Form::select('service', $service ?? null, '', ['class' => 'form-control', 'required' => true]) !!}
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-3 col-md-4 col-sm-12 col-12">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary flex-fill" data-bs-toggle="tooltip"
                                        title="{{ __('Apply') }}" id="applyfilter">
                                        <i class="ti ti-search" aria-hidden="true"></i>
                                        <span>{{ __('Apply') }}</span>
                                    </button>
                                    <a href="{{ route('appointment.index') }}" class="btn btn-sm btn-danger flex-fill" id="clearfilter"
                                        data-bs-toggle="tooltip" title="{{ __('Reset') }}">
                                        <i class="ti ti-refresh text-white" aria-hidden="true"></i>
                                        <span>{{ __('Reset') }}</span>
                                    </a>
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
    <script src="{{ asset('assets/js/bootstrap-datepicker.js') }}"></script>
    <script>
        $(function () {
            $(document).on('click', '#sendDataButton a', function (e) {
                e.preventDefault();
                var url = $(this).data('url');
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    beforeSend: function () { $('.loader-wrapper').removeClass('d-none'); },
                    success: function (response) {
                        $('.loader-wrapper').addClass('d-none');
                        toastrs('Success', response.message, 'success');
                        location.reload();
                    },
                    error: function (xhr) {
                        $('.loader-wrapper').addClass('d-none');
                        toastrs('Error', xhr.responseJSON?.error ?? 'Error', 'error');
                    }
                });
            });

            // Date/service filter → reload with querystring.
            $(document).on('click', '#applyfilter', function () {
                var date = $('#date').val();
                var service = $('#service').val();
                var url = new URL(window.location.href);
                if (date) url.searchParams.set('date', date); else url.searchParams.delete('date');
                if (service) url.searchParams.set('service', service); else url.searchParams.delete('service');
                window.location.href = url.toString();
            });
        });
    </script>
@endpush

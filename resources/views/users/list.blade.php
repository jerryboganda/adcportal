@extends('layouts.main')

@php
    // Single-clinic app: subscriber naming removed.
    $plural_name = __('Users');
    $singular_name = __('User');
@endphp

@section('page-title')
    {{ $plural_name }}
@endsection

@section('page-breadcrumb')
    {{ $plural_name }}
@endsection

@section('page-action')
    <div class="d-flex gap-2">
        @if (false)
            @permission('user import')
                @include('import-export::import.button', ['module' => 'users'])
            @endpermission
            @permission('user export')
                @include('import-export::export.button', ['module' => 'users'])
            @endpermission
        @endif
        @permission('user logs history')
            <a href="{{ route('users.userlog.history') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                data-bs-placement="top" title="{{ __('User Logs History') }}" aria-label="{{ __('User Logs History') }}"><i class="ti ti-user-check" aria-hidden="true"></i>
            </a>
        @endpermission
        @permission('user manage')
            <a href="{{ route('users.index') }}" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Grid View') }}"
                class="btn btn-sm btn-primary btn-icon" aria-label="{{ __('Grid View') }}">
                <i class="ti ti-layout-grid" aria-hidden="true"></i>
            </a>
        @endpermission
        @permission('user create')
            <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                data-title="{{ __('Create New ' . $singular_name) }}" data-url="{{ route('users.create') }}"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}" aria-label="{{ __('Create New '.$singular_name) }}">
                <i class="ti ti-plus" aria-hidden="true"></i>
            </a>
        @endpermission
    </div>
    @endsection
    @section('content')
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-xl-12">
                <x-datatable :dataTable="$dataTable" />
            </div>
        </div>
        <!-- [ Main Content ] end -->
    @endsection

    @push('scripts')
        <script>
            "use strict";
            $(document).on('change', '#password_switch', function() {
                if ($(this).is(':checked')) {
                    $('.ps_div').removeClass('d-none');
                    $('#password').attr("required", true);

                } else {
                    $('.ps_div').addClass('d-none');
                    $('#password').val(null);
                    $('#password').removeAttr("required");
                }
            });
            $(document).on('click', '.login_enable', function() {
                setTimeout(function() {
                    $('.modal-body').append($('<input>', {
                        type: 'hidden',
                        val: 'true',
                        name: 'login_enable'
                    }));
                }, 1000);
            });
        </script>
    @endpush

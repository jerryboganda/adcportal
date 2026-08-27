@extends('layouts.main')
@section('page-title')
    {{ __('Email Templates') }}
@endsection
@section('page-breadcrumb')
    {{ __('Email Templates') }}
@endsection
@section('page-action')
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="booking-data-table">
                        <table class="table datatable pc-dt-simple" id="d">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th class="email-tamp">{{ __('Module') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($email_templates as $email_template)
                                    <tr>
                                        <td style="width:50%;">{{ $email_template->name }}</td>
                                        <td class="text-capitalize" style="width:50%;">{{ Module_Alias_Name($email_template->module_name) }}
                                        </td>
                                        <td class="text-end">
                                            <div class="action-btn">
                                                <a href="{{ route('manage.email.language', [$email_template->id, getActiveLanguage()]) }}"
                                                    class="btn btn-sm bg-warning align-items-center"
                                                   title="{{ __('View') }}"
                                                    data-bs-toggle="tooltip" aria-label="{{ __('View') }}">
                                                    <i class="ti ti-eye text-white" aria-hidden="true"></i>
                                                </a>
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
        <!-- [ basic-table ] end -->
    </div>
@endsection

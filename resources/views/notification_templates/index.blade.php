@extends('layouts.main')
@section('page-title')
    {{ __('Notification Templates') }}
@endsection
@section('page-breadcrumb')
    {{ __('Notification Templates') }}
@endsection
@section('page-action')
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="col-md-12 d-flex align-items-center justify-content-between justify-content-md-end mb-4">
                <ul class="nav nav-pills nav-fill cust-nav information-tab" id="pills-tab" role="tablist">
                    @foreach ($activeNotifications as $key => $value)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="{{ $key }}" data-bs-toggle="pill"
                                data-bs-target="#{{ $key }}-tab" type="button">{{ $key }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    @if ($notifications->isEmpty())
                        <tr>
                            <td colspan="10" class="text-center">
                                <div class="text-center">
                                    <i class="fas fa-folder-open text-primary fs-40"></i>
                                    <h2>{{ __('Opps...') }}</h2>
                                    <h6> {!! __('No Data Found') !!} </h6>
                                </div>
                            </td>
                        </tr>
                    @endif
                    <div class="tab-content" id="pills-tabContent">
                        @foreach ($activeNotifications as $key => $notification)
                            <div class="tab-pane fade show " id="{{$key}}-tab" role="tabpanel"
                                aria-labelledby="pills-user-tab-1">
                                <div class="table-responsive">
                                    <table class="table mb-0 pc-dt-simple" id="{{$key}}-notify">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Name') }}</th>
                                                <th class="email-tamp">{{ __('Module') }}</th>
                                                <th class="text-end">{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($notification as $value)
                                                <tr>
                                                    <td style="width: 50%;">{{ $value->action }}</td>
                                                    <td class="text-capitalize" style="width: 50%;">{{ Module_Alias_Name($value->module) }}</td>
                                                    <td class="text-end">
                                                        <div class="action-btn">
                                                            <a href="{{ route('notification-template.show', [$value->id, getActiveLanguage()]) }}"
                                                                class="btn btn-sm  bg-warning align-items-center"
                                                                data-bs-toggle="tooltip" data-bs-placement="top"
                                                                title="{{ __('Manage Your :key Message', ['key' => $key]) }}">
                                                                <i class="ti ti-eye text-white"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $activeModule = '';
        foreach ($notifications as $key => $value) {
            $txt = false;
            if ($txt == true) {
                $activeModule = $key;
                break;
            }
        }
    @endphp

    <script>
        $(document).ready(function() {
            var moduleName = '{{ $activeModule }}';
            if (moduleName == 'Slack') {
                $('#Slack').addClass('active');
                $('#Slack-tab').addClass('active');
            } else if (moduleName == 'Telegram') {
                $('#Telegram').addClass('active');
                $('#Telegram-tab').addClass('active');
            } else if (moduleName == 'Twilio') {
                $('#Twilio').addClass('active');
                $('#Twilio-tab').addClass('active');
            } else if (moduleName == 'Whatsapp') {
                $('#Whatsapp').addClass('active');
                $('#Whatsapp-tab').addClass('active');
            } else if (moduleName == 'WhatsAppAPI') {
                $('#WhatsAppAPI').addClass('active');
                $('#WhatsAppAPI-tab').addClass('active');
            }else if (moduleName == 'SMS') {
                $('#SMS').addClass('active');
                $('#SMS-tab').addClass('active');
            }
        });
    </script>
@endpush

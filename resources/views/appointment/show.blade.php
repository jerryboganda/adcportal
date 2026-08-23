<div class="modal-body">
    <div class="row row-gaps appointment-detail-popup">
        <div class="col-lg-6 col-12 ">
                <address class="mb-0 text-sm">
                    <dl class="mb-0">
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Date:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ $appointment->date ?? ''}}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Duration:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ $appointment->time ?? ''}}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Customer:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ !empty($appointment->CustomerData) ? $appointment->CustomerData->name : ($appointment->name ?? 'Guest') . ' (Guest)' }}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Email:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ !empty($appointment->CustomerData) ? $appointment->CustomerData->customer->email : $appointment->email }}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Contact:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ !empty($appointment->CustomerData) ? $appointment->CustomerData->customer->mobile_no : $appointment->contact }}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Staff:') }}</dt>
                        <dd class="text-md mb-0">
                            {{ !empty($appointment->StaffData) ? $appointment->StaffData->name : '-' }}
                        </dd>
                        </div>
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Amount:') }}</dt>
                        <dd class="text-md mb-0">{{ !empty($appointment->payment) ? currency_format_with_sym($appointment->payment->amount) : ((false || false) && !empty($appointment->payments($appointment->id)) ? currency_format_with_sym($appointment->payments($appointment->id)->amount) : currency_format_with_sym(0)) }}</dd>
                        </div>
                        @if (false)
                        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                            <dt class="text-md mb-0 h6">{{ __('Coupon Price:') }}</dt>
                            <dd class="text-md mb-0">{{ !empty($appointment->payment) ? currency_format_with_sym($appointment->payment->coupon_amount) : currency_format_with_sym(0) }}</dd>
                        </div>
                            @endif
                    </dl>
                </address>
        </div>
        <div class="col-lg-6 col-12 ">
            <div class="">
                <dl class="mb-0">
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                    <dt class="text-md mb-0 h6">{{ __('Service:') }}</dt>
                    <dd class="text-md mb-0">
                        {{ !empty($appointment->ServiceData) ? $appointment->ServiceData->name : '-' }}
                    </dd>
                    </div>
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                    <dt class="text-md mb-0 h6">{{ __('Location:') }}</dt>
                    <dd class="text-md mb-0">
                        {{ !empty($appointment->LocationData) ? $appointment->LocationData->name : '-' }}
                    </dd>
                    </div>
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                    <dt class="text-md mb-0 h6">{{ __('Payment:') }}</dt>
                    <dd class="text-md mb-0">
                        {{ !empty($appointment->payment_type) ? $appointment->payment_type : '-' }}
                    </dd>
                    </div>
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                    <dt class="text-md mb-0 h6">{{ __('Status:') }}</dt>
                    <dd class="text-md mb-0">
                        {{ !empty($appointment->StatusData) ? $appointment->StatusData->title : ((false && $appointment->appointment_status == 'Waiting List') ? $appointment->appointment_status : 'Pending') }}
                    </dd>
                    </div>
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                    <dt class="text-md mb-0 h6">{{ __('Referred By:') }}</dt>
                    <dd class="text-md mb-0">
                        {{ !empty($appointment->ReferrerData) ? $appointment->ReferrerData->name : '-' }}
                    </dd>
                    </div>
                    @if (false)
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                        <dt class="text-md mb-0 h6">{{ __('Final Price:') }}</dt>
                        <dd class="text-md mb-0">{{ !empty($appointment->payment) ? currency_format_with_sym($appointment->payment->final_amount) : currency_format_with_sym(0) }}</dd>
                    </div>
                        @endif
                    @if (false)
                    <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
                            <dt class="text-md mb-0 h6">{{ __('Tax Price:') }}</dt>
                            <dd class="text-md mb-0">{{ !empty($appointment->payment) ? currency_format_with_sym($appointment->payment->tax_amount) : currency_format_with_sym(0) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    @if (!empty($appointment->attachment))
        <div class="row">
            <div class="col-12">
                <dl class="mb-0 row align-items-center mt-2">
                    <dt class="col-12 h6">{{ __('Attachment') }}</dt>
                    <dd class="text-md col-md-3 col-sm-7">
                        <img src="{{ check_file($appointment->attachment) ? get_file($appointment->attachment) : '-' }}"
                            alt={{ str_replace(' ', '_', basename($appointment->attachment)) }}
                            class="img-fluid rounded-2" width="100%">
                    </dd>
                    <dd class="gap-2 col-md-9 col-sm-5 d-flex align-items-center header-btn-wrp">
                        <div>
                            <form method="POST"
                                action="{{ route('appointment.attachment.destroy', $appointment->id) }}"
                                id="user-form-{{ $appointment->id }}">
                                @csrf
                                <button type="button"
                                    class="bg-danger btn btn-sm align-items-center show_confirm"
                                    data-bs-toggle="tooltip" title='Delete'>
                                    <span class="text-white"> <i class="ti ti-trash"></i></span>
                                </button>
                            </form>
                        </div>
                        <a download
                            href="{{ check_file($appointment->attachment) ? get_file($appointment->attachment) : '-' }}"
                            class="action-btn btn-primary btn btn-sm align-items-center">
                            <i class="ti ti-download" data-toggle="popover" title="{{ __('Download') }}"></i>
                        </a>
                    </dd>
                </dl>
            </div>
        </div>
        <hr>
    @endif

    @if (!empty($appointment->custom_field))
        @php
            $customfields = json_decode($appointment->custom_field, true);
        @endphp
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">{{ __('Custom Details') }}</h5>
                @foreach ($customfields as $key => $value)
                    <dl class="row align-items-center">
                        <dt class="col-sm-5 h6 mb-0">{{ $key }}:</dt>
                        <dd class="col-sm-7 mb-0">
                            {{ $value }}
                        </dd>
                    </dl>
                @endforeach
            </div>
        </div>
    @endif
</div>

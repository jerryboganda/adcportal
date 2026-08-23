<div class="modal-body">


    <div class="row-gaps appointment-detail-popup">
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Customer') }} : </div>
            <p class="text-md mb-0">
                {{ !empty($appointments->CustomerData) ? $appointments->CustomerData->name : ($appointments->name ?? 'Guest') . ' (Guest)' }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Staff') }} :</div>
            <p class="text-md mb-0">
                {{ !empty($appointments->StaffData) ? $appointments->StaffData->name : '-' }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Service') }} :</div>
            <p class="text-md mb-0">
                {{ !empty($appointments->ServiceData) ? $appointments->ServiceData->name : '-' }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Location') }} :</div>
            <p class="text-md mb-0">
                {{ !empty($appointments->LocationData) ? $appointments->LocationData->name : '-' }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Payment Type') }} :</div>
            <p class="text-md mb-0">
                {{ !empty($appointments->payment_type) ? $appointments->payment_type : '-' }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Status') }} :</div>
            <p class="text-md mb-0">
                {{ !empty($appointments->StatusData) ? $appointments->StatusData->title : (false && $appointments->appointment_status == 'Waiting List' ? $appointments->appointment_status : 'Pending') }}
            </p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Date') }} :</div>
            <p class="text-md mb-0">{{ $appointments->date }}</p>
        </div>
        <div class="appointment-detail-item d-flex flex-wrap align-items-start gap-2">
            <div class="form-control-label text-md mb-0 h6">{{ __('Time') }}:</div>
            <p class="text-md mb-0">{{ $appointments->time }}</p>
        </div>
    </div>
</div>

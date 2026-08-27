<div class="action-btn me-2">
    <a href="#" class="btn btn-sm btn-primary-subtle d-inline align-items-center"
        data-url="{{ route('create.online.appointment', ['serviceId' => $service->id,'businessId' => $businessId]) }}" class="dropdown-item" data-size="md"
        data-ajax-popup="true" data-title="{{ __('Online Appointment') }}" data-bs-toggle="tooltip"
        data-bs-original-title="{{ __('Online Appointment') }}">
        <span class="text-white"> <i class="ti ti-video" aria-hidden="true"></i></span></a>
</div>

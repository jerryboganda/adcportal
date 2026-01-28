<div class="d-flex">
@if (module_is_active('Tip'))
    @include('tip::tip.tip_action', ['object' => $Appointment])
@endif

@if (module_is_active('GoogleCalendar') && isset($company_settings['google_calendar_enable']) && $company_settings['google_calendar_enable'] == 'on' && $Appointment->is_sync == 1)
    <div class="action-btn me-2" id="sendDataButton">
        <a href="#" class="btn btn-sm bg-primary-subtle d-inline align-items-center"
            data-url="{{ route('google.calendar.sync', $Appointment->id) }}" class="dropdown-item" data-ajax-popup="false"
            data-title="{{ __('Edit Appointment') }}" data-bs-toggle="tooltip" data-size="lg"
            data-bs-original-title="{{ __('Sync Google Calendar') }}">
            <span class="text-primary-emphasis">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"
                    fill="#FFFFFF">
                    <path
                        d="M160-160v-80h110l-16-14q-52-46-73-105t-21-119q0-111 66.5-197.5T400-790v84q-72 26-116 88.5T240-478q0 45 17 87.5t53 78.5l10 10v-98h80v240H160Zm400-10v-84q72-26 116-88.5T720-482q0-45-17-87.5T650-648l-10-10v98h-80v-240h240v80H690l16 14q49 49 71.5 106.5T800-482q0 111-66.5 197.5T560-170Z" />
                </svg>
            </span>
        </a>
    </div>
@endif

@if (module_is_active('OutlookCalendar') &&
        isset($company_settings['outlook_calendar_enable']) &&
        $company_settings['outlook_calendar_enable'] == 'on' &&
        $Appointment->outlook_is_sync == 1)
    <div class="action-btn me-2 o-calender me-2" id="sendDataButton">
        <a href="#" class="btn btn-sm d-inline  bg-secondary align-items-center"
            data-url="{{ route('outlook.calendar.sync', $Appointment->id) }}" class="dropdown-item"
            data-ajax-popup="false" data-title="{{ __('Edit Appointment') }}" data-bs-toggle="tooltip" data-size="lg"
            data-bs-original-title="{{ __('Sync Outlook Calendar') }}">
            <span class="text-white">
                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="100" height="100"
                    viewBox="0 0 32 32">
                    <path
                        d="M 15.851562 4 L 15.744141 4.0234375 L 3.9609375 6.6425781 L 3.9609375 25.357422 L 15.851562 28 L 17.960938 28 L 17.960938 25 L 28 25 L 28 7 L 17.960938 7 L 17.960938 4 L 15.851562 4 z M 15.960938 6.0253906 L 15.960938 25.976562 L 5.9609375 23.751953 L 5.9609375 8.2460938 L 15.960938 6.0253906 z M 17.960938 9 L 26 9 L 26 11 L 17.960938 11 L 17.960938 9 z M 10.544922 11 C 9.4649219 11 8.5983594 11.473016 7.9433594 12.416016 C 7.2883594 13.360016 6.9609375 14.596 6.9609375 16.125 C 6.9609375 17.572 7.2847344 18.747438 7.9277344 19.648438 C 8.5717344 20.550438 9.4057344 21 10.427734 21 C 11.476734 21 12.328422 20.533516 12.982422 19.603516 C 13.634422 18.672516 13.960938 17.449687 13.960938 15.929688 C 13.960938 14.450688 13.645578 13.259469 13.017578 12.355469 C 12.388578 11.452469 11.563922 11 10.544922 11 z M 10.492188 12.996094 C 10.947188 12.996094 11.305406 13.263781 11.566406 13.800781 C 11.829406 14.336781 11.960937 15.085828 11.960938 16.048828 C 11.960938 16.973828 11.825688 17.696797 11.554688 18.216797 C 11.284688 18.737797 10.918984 18.996094 10.458984 18.996094 C 10.011984 18.996094 9.651 18.728359 9.375 18.193359 C 9.099 17.656359 8.9609375 16.929766 8.9609375 16.009766 C 8.9609375 15.102766 9.099 14.373266 9.375 13.822266 C 9.651 13.272266 10.023187 12.996094 10.492188 12.996094 z M 17.960938 13 L 26 13 L 26 23 L 17.960938 23 L 17.960938 21 L 20 21 L 20 19 L 17.960938 19 L 17.960938 17 L 20 17 L 20 15 L 17.960938 15 L 17.960938 13 z M 22 15 L 22 17 L 24 17 L 24 15 L 22 15 z M 22 19 L 22 21 L 24 21 L 24 19 L 22 19 z">
                    </path>
                </svg>
            </span>
        </a>
    </div>
@endif

@if (module_is_active('EasyDepositPayments'))
    @include('easy-deposit-payments::deposit.payment')
@endif

@if (module_is_active('AdditionalServices'))
    @permission('additional quanitty edit')
        @if ($Appointment->additional_service_id)
            @include('additional-services::additional_service.appointment_action', [
                'appointment' => $Appointment,
            ])
        @endif
    @endpermission
@endif

@permission('appointment edit')
    {{-- Print Token Button --}}
    <div class="action-btn me-2">
        <a href="{{ route('appointment.print-token', $Appointment->id) }}" 
            target="_blank"
            class="btn btn-sm bg-success d-inline align-items-center"
            data-bs-toggle="tooltip" 
            data-bs-original-title="{{ __('Print Token') }}">
            <span class="text-white"><i class="ti ti-printer"></i></span>
        </a>
    </div>
    {{-- Reports Button --}}
    <div class="action-btn me-2">
        <a href="#" class="btn btn-sm bg-warning d-inline align-items-center"
            data-url="{{ route('appointment.reports', $Appointment->id) }}" 
            data-ajax-popup="true"
            data-title="{{ __('Appointment Reports') }}" 
            data-bs-toggle="tooltip" 
            data-size="lg"
            data-bs-original-title="{{ __('Reports') }}">
            <span class="text-white"><i class="ti ti-file-text"></i></span>
        </a>
    </div>
    {{-- Edit Button --}}
    <div class="action-btn me-2">
        <a href="#" class="btn btn-sm bg-info  d-inline align-items-center"
            data-url="{{ route('appointment.edit', $Appointment->id) }}" class="dropdown-item" data-ajax-popup="true"
            data-title="{{ __('Edit Appointment') }}" data-bs-toggle="tooltip" data-size="lg"
            data-bs-original-title="{{ __('Edit') }}">
            <span class="text-white"> <i class="ti ti-pencil"></i></span></a>
    </div>
@endpermission

@permission('appointment delete')
    <div class="action-btn">
        <form method="POST" action="{{ route('appointment.destroy', $Appointment->id) }}"
            id="user-form-{{ $Appointment->id }}">
            @csrf
            @method('DELETE')
            <input name="_method" type="hidden" value="DELETE">
            <button type="button" class="btn btn-sm bg-danger d-inline align-items-center show_confirm"
                data-bs-toggle="tooltip" title='Delete'>
                <span class="text-white"> <i class="ti ti-trash"></i></span>
            </button>
        </form>
    </div>
@endpermission
</div>

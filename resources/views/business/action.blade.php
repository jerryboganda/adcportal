<div class="d-flex">
<div class="action-btn me-2">
    <a href="javascript:void(0)" class="btn btn-sm  bg-primary align-items-center cp_link"
        data-link="{{ route('appointments.form') }}" data-bs-placement="top" data-bs-toggle="tooltip"
        data-bs-original-title="{{ __('Click To Copy Form Link') }}">
        <i class="ti ti-link text-white"></i>
    </a>
</div>
{{-- @permission('appointment manage') --}}
<div class="action-btn me-2">
    <a href="{{ route('appointment.index', ['business' => $business->id]) }}"
        class="btn btn-sm  bg-secondary align-items-center" data-bs-toggle="tooltip" title='Appointments'> <span
            class="text-white">
            <i class="ti ti-credit-card" aria-hidden="true"></i></span></a>
</div>
{{-- @endpermission --}}
@permission('subscriber manage')
    <div class="action-btn me-2">
        <a href="{{ route('subscribes.index', ['business' => $business->id]) }}"
            class="btn btn-sm bg-dark align-items-center" data-bs-toggle="tooltip" title='Subscribers'> <span
                class="text-white">
                <i class="ti ti-mail" aria-hidden="true"></i></span></a>
    </div>
@endpermission
@permission('contact manage')
    <div class="action-btn me-2">
        <a href="{{ route('contacts.index', ['business' => $business->id]) }}"
            class="btn btn-sm bg-warning align-items-center" data-bs-toggle="tooltip" title='Contacts'> <span
                class="text-white"> <i class="ti ti-phone" aria-hidden="true"></i></span></a>
    </div>
@endpermission
@permission('business update')
    <div class="action-btn me-2">
        <a href="{{ route('business.manage', $business->id) }}" class="btn btn-sm  bg-info align-items-center"
            data-bs-toggle="tooltip" title='Manage Business'> <span class="text-white"> <i
                    class="ti ti-corner-up-left" aria-hidden="true"></i></span></a>
    </div>
@endpermission
@permission('business delete')
    <div class="action-btn">
        <form method="POST" action="{{ route('business.destroy', $business->id) }}" id="user-form-{{ $business->id }}">
            @csrf
            @method('DELETE')
            <input name="_method" type="hidden" value="DELETE">
            <button type="button" class="btn btn-sm  bg-danger align-items-center show_confirm"
                data-bs-toggle="tooltip" title='Delete'>
                <span class="text-white"> <i class="ti ti-trash" aria-hidden="true"></i></span>
            </button>
        </form>
    </div>
@endpermission
</div>

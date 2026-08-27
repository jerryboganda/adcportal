<div class="d-flex">
@if ($user->is_disable == 1)
    {{-- Single-clinic app: Admin Hub / Login As Company / Business Link buttons removed. --}}
    @permission('user reset password')
        <div class="action-btn me-2">
            <a href="#" class="btn btn-sm d-inline bg-warning  align-items-center"
                data-url="{{ route('users.reset', \Crypt::encrypt($user->id)) }}" data-ajax-popup="true"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Reset Password') }}"
                data-title="{{ __('Reset Password') }}"> <span class="text-white"><i class="ti ti-adjustments" aria-hidden="true"></i></a>
        </div>
    @endpermission
    @permission('user login manage')
        @if ($user->is_enable_login == 1)
            <div class="action-btn me-2">
                <a href="{{ route('users.login', \Crypt::encrypt($user->id)) }}"
                    class="btn btn-sm d-inline bg-danger align-items-center" data-bs-toggle="tooltip"
                    data-bs-original-title="{{ __('Login Disable') }}"> <span class="text-white"><i
                            class="ti ti-road-sign" aria-hidden="true"></i></a>
            </div>
        @elseif ($user->is_enable_login == 0 && $user->password == null)
            <div class="action-btn me-2">
                <a href="#" data-url="{{ route('users.reset', \Crypt::encrypt($user->id)) }}" data-ajax-popup="true"
                    data-size="md" class="btn btn-sm bg-secondary d-inline align-items-center login_enable"
                    data-title="{{ __('New Password') }}" data-bs-toggle="tooltip"
                    data-bs-original-title="{{ __('New Password') }}"> <span class="text-white"><i
                            class="ti ti-road-sign" aria-hidden="true"></i></a>
            </div>
        @else
            <div class="action-btn me-2">
                <a href="{{ route('users.login', \Crypt::encrypt($user->id)) }}"
                    class="btn btn-sm d-inline bg-success align-items-center login_enable" data-bs-toggle="tooltip"
                    data-bs-original-title="{{ __('Login Enable') }}"> <span class="text-white"> <i
                            class="ti ti-road-sign" aria-hidden="true"></i>
                </a>
            </div>
        @endif
    @endpermission
    @permission('user edit')
        <div class="action-btn me-2">
            <a href="#" class="btn btn-sm d-inline bg-info  align-items-center"
                data-url="{{ route('users.edit', $user->id) }}" class="dropdown-item" data-ajax-popup="true"
                data-title="{{ __('Edit User') }}"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"> <span class="text-white"> <i
                        class="ti ti-pencil" aria-hidden="true"></i></span></a>
        </div>
    @endpermission
    @permission('user delete')
        <div class="action-btn me-2">
            {{ Form::open(['route' => ['users.destroy', $user->id], 'class' => 'm-0']) }}
            @method('DELETE')
            <a href="#" class="btn btn-sm  bg-danger align-items-center bs-pass-para show_confirm" data-bs-toggle="tooltip"
                title="" data-bs-original-title="Delete" aria-label="Delete"
                data-confirm-yes="delete-form-{{ $user->id }}"><i class="ti ti-trash text-white text-white"></i></a>
            {{ Form::close() }}
        </div>
    @endpermission
    @if(admin_setting('email_verification') == 'on' && $user->email_verified_at == null)
        <div class="action-btn">
            <a href="{{ route('user.verified', $user->id) }}" class="btn btn-sm d-inline-flex align-items-center bg-primary-subtle"  data-bs-toggle="tooltip" data-bs-original-title="{{ __('Verified Now') }}"> <span class="text-white"><i class="ti ti-checks" aria-hidden="true"></i></a>
        </div>
    @endif
@else
    <div class="text-center">
        <i class="ti ti-lock" aria-hidden="true"></i>
    </div>
@endif
</div>

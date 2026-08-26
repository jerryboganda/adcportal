@extends('layouts.auth')
@section('page-title')
    {{__('Login')}}
@endsection
{{-- Language selector removed — English only --}}
@php
    $admin_settings = getAdminAllSetting();
@endphp

@section('content')
<div class="card">
    <div class="card-body">
        <div class="">
            <h2 class="mb-3 f-w-600">{{ __('Login') }}</h2>
        </div>
        <form method="POST" action="{{ route('login') }}" class="needs-validation" novalidate="" id="form_data">
            @csrf
            <div>
                <div class="form-group mb-3">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input id="email" type="email" class="form-control  @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="{{ __('E-Mail Address') }}" required autofocus autocomplete="email">
                    @error('email')
                        <span class="error invalid-email text-danger" role="alert">
                            <small>{{ $message }}</small>
                        </span>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">{{ __('Password') }}</label>
                    <input id="password" type="password" class="form-control  @error('password') is-invalid @enderror" name="password" placeholder="{{ __('Password') }}" required autocomplete="current-password">
                    @error('password')
                    <span class="invalid-feedback d-block" role="alert">
                        <small>{{ $message }}</small>
                    </span>
                    @enderror
                    @if (Route::has('password.request'))
                    <div class="mt-2">
                        <a href="{{ route('password.request') }}" class="small text-primary text-underline--dashed border-primar">{{ __('Forgot Your Password?') }}</a>
                    </div>
                    @endif
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-block mt-2 login_button" tabindex="4">{{ __('Login') }}</button>
                </div>
                {{-- Single-clinic app: public registration disabled. --}}
            </div>
        </form>
    </div>
</div>
@endsection
@push('script')
<script>
    "use strict";
    $(document).on('submit', '#form_data', function(e) {
            $(".login_button").prop("disabled", true);
            return true;
        });
    </script>
@endpush

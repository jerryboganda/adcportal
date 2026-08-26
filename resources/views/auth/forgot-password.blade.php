@extends('layouts.auth')
@section('page-title')
    {{ __('Reset Password') }}
@endsection
{{-- Language selector removed — English only --}}
@php
    $admin_settings = getAdminAllSetting();
@endphp

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="">
                <h2 class="mb-3 f-w-600">{{ __('Forgot Password') }}</h2>
                @if (session('status'))
                    <div class="alert alert-primary">
                        {{ session('status') }}
                    </div>
                @endif
                <p class="text-xs text-muted">{{ __('We will send a link to reset your password') }}</p>
            </div>
            <form method="POST" action="{{ route('password.email') }}" id="form_data">
                @csrf
                <div class="">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                            name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="{{ __('Email')}}">
                        @error('email')
                            <span class="error invalid-email text-danger" role="alert">
                                <small>{{ $message }}</small>
                            </span>
                        @enderror
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary btn-submit btn-block mt-2">{{ __('Send Password Reset Link') }}
                        </button>
                    </div>
                    <p class="my-4 text-center">
                        <a href="{{ route('login') }}" class="text-primary">
                            <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>{{ __('Back to login') }}
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
@endsection


@extends('layouts.auth')
@section('page-title')
    {{ __('Verify Email') }}
@endsection
{{-- Language selector removed — English only --}}
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="">
                <h2 class="mb-3 f-w-600">{{ __('Verify Email') }}</h2>
                <h6>{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
                </h6>
            </div>
            @if (session('status') == 'verification-link-sent')
                <div class="mb-4 font-medium text-sm text-success">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @elseif(session('status') == 'verification-link-not-sent')
                <div class="mb-4 font-medium text-sm text-danger">
                    {{ __("Oops! We encountered an issue while attempting to send the email. It seems there's a problem with the mail server's SMTP (Simple Mail Transfer Protocol). Please review the SMTP settings and configuration to resolve the problem.") }}
                </div>
            @endif
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-block mt-2"
                        tabindex="4">{{ __('Resend Verification Email') }}</button>
                </div>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <div class="d-flex justify-content-center">
                    <button type="submit" class="btn btn-danger btn-block mt-2">
                        {{ __('LogOut') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('custom-scripts')
@endpush

@extends('layouts.auth')

@section('page-title')
    {{ __('Confirm Password') }}
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="">
            <h2 class="mb-2 f-w-600">{{ __('Confirm Password') }}</h2>
            <p class="text-muted mb-4">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
        </div>
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="form-group mb-3">
                <label class="form-label" for="password">{{ __('Password') }}</label>
                <input id="password" type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    name="password" value="{{ old('password') }}"
                    placeholder="{{ __('Password') }}" required autocomplete="current-password" autofocus>
                @error('password')
                    <span class="invalid-feedback d-block" role="alert">
                        <small>{{ $message }}</small>
                    </span>
                @enderror
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-block mt-2">{{ __('Confirm') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

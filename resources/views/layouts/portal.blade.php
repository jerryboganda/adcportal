@php
    $company_settings = getCompanyAllSetting();
    $admin_settings = getAdminAllSetting();
    $siteTitle = !empty($company_settings['title_text']) ? $company_settings['title_text'] : (!empty($admin_settings['title_text']) ? $admin_settings['title_text'] : 'AMAD Diagnostic Centre');
    $favicon = isset($company_settings['favicon']) ? $company_settings['favicon'] : (isset($admin_settings['favicon']) ? $admin_settings['favicon'] : 'uploads/logo/favicon.png');
    $logo = !empty($company_settings['light_logo']) ? $company_settings['light_logo'] : (!empty($admin_settings['light_logo']) ? $admin_settings['light_logo'] : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', __('My Studies')) | {{ $siteTitle }}</title>
    <meta name="description" content="@yield('meta-description', __('Patient portal — track your imaging studies and download reports.'))">
    <link rel="icon" href="{{ check_file($favicon) ? get_file($favicon) : get_file('uploads/logo/favicon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('css')
</head>

<body>
    <header class="adc-topbar" data-adc-print-hide>
        <div class="container">
            <div class="adc-topbar-inner">
                <a class="adc-brand" href="{{ route('portal.studies') }}">
                    @if ($logo && check_file($logo))
                        <img src="{{ get_file($logo) }}" alt="{{ $siteTitle }}">
                    @else
                        <i class="ti ti-radiology fs-3" style="color: var(--adc-primary)" aria-hidden="true"></i>
                    @endif
                    <span>{{ $siteTitle }}</span>
                </a>
                <div class="adc-userchip">
                    <i class="ti ti-user-circle fs-4" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">{{ optional(Auth::user())->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                            <i class="ti ti-power" aria-hidden="true"></i><span>{{ __('Logout') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="adc-page">
        @if ($message = Session::get('success'))
            <div class="alert alert-success" role="status">{{ $message }}</div>
        @endif
        @if ($message = Session::get('error'))
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @endif
        @yield('content')
    </main>

    <footer class="adc-footnote" data-adc-print-hide>
        &copy; {{ date('Y') }} {{ $siteTitle }}
    </footer>

    @stack('scripts')
</body>

</html>

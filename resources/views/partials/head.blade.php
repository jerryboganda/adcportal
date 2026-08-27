@php
    $company_settings = getCompanyAllSetting();
    $admin_settings = getAdminAllSetting();
    $favicon = isset($company_settings['favicon']) ? $company_settings['favicon'] : (isset($admin_settings['favicon']) ? $admin_settings['favicon'] : 'uploads/logo/favicon.png');
@endphp
<head>
    <title>@yield('page-title') | {{ !empty($company_settings['title_text']) ? $company_settings['title_text'] : (!empty($admin_settings['title_text']) ? $admin_settings['title_text'] :'AMAD Diagnostic Centre') }}</title>

    <meta name="title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta name="keywords" content="{{ !empty($admin_settings['meta_keywords']) ? $admin_settings['meta_keywords'] : 'Multi Business Appointment Booking and Scheduling' }}">
    <meta name="description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'AMAD Diagnostic Centre - Professional healthcare and diagnostic services in Gujranwala.'}}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ env('APP_URL') }}">
    <meta property="og:title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta property="og:description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'AMAD Diagnostic Centre - Professional healthcare and diagnostic services in Gujranwala.'}} ">
    <meta property="og:image" content="{{ get_file( (!empty($admin_settings['meta_image'])) ? (check_file($admin_settings['meta_image'])) ?  $admin_settings['meta_image'] : 'uploads/meta/meta_image.png' : 'uploads/meta/meta_image.png'  ) }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ env('APP_URL') }}">
    <meta property="twitter:title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta property="twitter:description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'AMAD Diagnostic Centre - Professional healthcare and diagnostic services in Gujranwala.'}} ">
    <meta property="twitter:image" content="{{ get_file( (!empty($admin_settings['meta_image'])) ? (check_file($admin_settings['meta_image'])) ?  $admin_settings['meta_image'] : 'uploads/meta/meta_image.png' : 'uploads/meta/meta_image.png'  ) }}">

    <meta name="author" content="{{  env('APP_NAME')  }}">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="url" content="{{ url('').'/'.config('chatify.routes.prefix') }}" data-user="{{ Auth::user()->id }}">

    <!-- Favicon -->
    <link rel="icon" href="{{ check_file($favicon) ? get_file($favicon) : get_file('uploads/logo/favicon.png') }}" type="image/x-icon" />

    <!-- Tabler Icons (single icon system) -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">

    <!-- Vendor CSS (datepicker, switches, summernote) -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/datepicker-bs5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/bootstrap-switch-button.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.css') }}">

    <!-- Customizer / last-mile -->
    <link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/fontawesome-iconpicker.min.css') }}">

    <style>
        :root {
            @if (isset($company_settings['color']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $company_settings['color']))
                --color-customColor: {{ $company_settings['color'] }};
            @endif
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/custom-color.css') }}">

    <!-- Legacy theme (kept for now; will be removed in a future release) -->
    @if ((isset($company_settings['site_rtl']) ? $company_settings['site_rtl'] : 'off')== 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-rtl.css') }}">
    @endif
    @if ((isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-dark.css') }}" id="main-style-link">
    @endif
    @if ((isset($company_settings['site_rtl']) ? $company_settings['site_rtl'] : 'off' )!= 'on' && (isset($company_settings['cust_darklayout']) ? $company_settings['cust_darklayout'] : 'off') != 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
    @else
        <link rel="stylesheet" href="" id="main-style-link">
    @endif
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">

    {{-- custom-css --}}
    @if(isset($admin_settings['custom_css']))
        <style type="text/css">
            {!! preg_replace('/<\/?(script|style)[^>]*>/i', '', htmlspecialchars_decode($admin_settings['custom_css'])) !!}
        </style>
    @endif

    {{-- Token-driven system (Bootstrap + tokens + overrides). MUST load last so overrides win. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('css')
    @stack('availabilitylink')
    <script src="{{ asset('js/jquery.min.js') }}"></script>
</head>

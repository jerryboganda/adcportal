@php
    $admin_settings = getAdminAllSetting();
    $temp_lang = \App::getLocale('lang');
    if($temp_lang == 'ar' || $temp_lang == 'he'){
        $rtl = 'on';
    }
    else {
        $rtl = isset($admin_settings['site_rtl']) ? $admin_settings['site_rtl'] : 'off';
    }

    $color = isset($admin_settings['color']) ? $admin_settings['color'] : 'theme-1';
    if(isset($admin_settings['color_flag']) && $admin_settings['color_flag'] == 'true')
    {
        $themeColor = 'custom-color';
    }
    else {
        $themeColor = $color;
    }

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{  $rtl == 'on'?'rtl':''}}">

<head>

    <title>@yield('page-title') | {{ !empty($admin_settings['title_text']) ? $admin_settings['title_text'] : config('app.name', 'ADC - Amad Diagnostic Centre') }}</title>

    <meta name="title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta name="keywords" content="{{ !empty($admin_settings['meta_keywords']) ? $admin_settings['meta_keywords'] : 'Multi Business Appointment Booking and Scheduling' }}">
    <meta name="description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'AMAD Diagnostic Centre - Professional healthcare and diagnostic services in Gujranwala.'}}"> 

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ env('APP_URL') }}">
    <meta property="og:title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta property="og:description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'AMAD Diagnostic Centre - Professional healthcare and diagnostic services in Gujranwala.'}} ">
    <meta property="og:image" content="{{ get_file( (!empty($admin_settings['meta_image'])) ? (check_file($admin_settings['meta_image'])) ?  $admin_settings['meta_image'] : 'uploads/meta/meta_image.png' : 'uploads/meta/meta_image.png'  ) }}{{'?'.time() }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ env('APP_URL') }}">
    <meta property="twitter:title" content="{{ !empty($admin_settings['meta_title']) ? $admin_settings['meta_title'] : 'AMAD Diagnostic Centre' }}">
    <meta property="twitter:description" content="{{ !empty($admin_settings['meta_description']) ? $admin_settings['meta_description'] : 'Multi Business Appointment Booking and Scheduling'}} ">
    <meta property="twitter:image" content="{{ get_file( (!empty($admin_settings['meta_image'])) ? (check_file($admin_settings['meta_image'])) ?  $admin_settings['meta_image'] : 'uploads/meta/meta_image.png' : 'uploads/meta/meta_image.png'  ) }}{{'?'.time() }}">

    <meta name="author" content="{{  env('APP_NAME')  }}">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="{{ (!empty($admin_settings['favicon']) && check_file($admin_settings['favicon'])) ? get_file($admin_settings['favicon']) : get_file('uploads/logo/favicon.png')}}{{'?'.time()}}" type="image/x-icon" />
     <!-- CSS Libraries -->
     <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">

      <!-- font css -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">
    <!-- vendor css -->
    <link rel="stylesheet" href="{{ asset('assets/css/customizer.css') }}">
    <!-- custom css -->

    <style>
        :root {
            --color-customColor: <?=$color ?>;
        }
    </style>
    <style>
        /* google recapcha  */
        .grecaptcha-badge{
        z-index: 2;
        }.border-grey {
        border: 1px solid #CBCBCB
        !important;
        }
        .upgrade-line hr {
        flex: 1;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/custom-color.css') }}">

    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    @if ( $rtl == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-rtl.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom-auth-rtl.css') }}" id="main-style-link">
    @else
        <link rel="stylesheet" href="{{ asset('css/custom-auth.css') }}" id="main-style-link">
    @endif

    @if((isset($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] : 'off') == 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style-dark.css') }}" id="main-style-link">
        <link rel="stylesheet" href="{{ asset('css/custom-auth-dark.css') }}" id="main-style-link">
    @endif

    @if( $rtl != 'on' && (isset($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] : 'off') != 'on')
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
    @endif

    <style>
        .navbar-brand .auth-navbar-brand
        {
            max-height: 38px !important;
        }
    </style>
</head>
<body class="{{ $themeColor }}">
    <div class="custom-login">
        <div class="login-bg-img">
            <img src="{{ asset('images/theme-1.svg') }}" class="login-bg-1">
            <img src="{{ asset('images/common.svg') }}" class="login-bg-2">
        </div>
        <div class="bg-login bg-primary"></div>
        <div class="custom-login-inner">
            <header class="dash-header">
                <nav class="navbar navbar-expand-md default">
                    <div class="container">
                        <div class="navbar-brand">
                            <a class="navbar-brand" href="{{ url('/') }}">
                                <img src="{{ get_file(sidebar_logo()) }}{{'?'.time()}}" alt="{{ config('app.name', 'ADC - Amad Diagnostic Centre') }}" class="navbar-brand-img auth-navbar-brand">
                            </a>
                        </div>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                            data-bs-target="#navbarlogin">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarlogin">
                            <ul class="navbar-nav align-items-center ms-auto mb-2 mb-lg-0">
                                {{-- WorkDo and Language buttons removed — English only --}}
                            </ul>
                        </div>
                    </div>
                </nav>
            </header>
            <main class="custom-wrapper">
                <div class="custom-row">
                    <div class="card">
                        @yield('content')
                    </div>
                </div>
            </main>
            <footer>
                <div class="auth-footer">
                    <div class="container">
                        <div class="row">
                            <div class="col-12">
                                <span>
                                    @php $authFooter = $admin_settings['footer_text'] ?? null; @endphp
                                    @if (!empty($authFooter))
                                        {{ $authFooter }} {{ date('Y') }}@if (!str_contains($authFooter, 'PolytronX')) <span class="mx-1">|</span> Powered By PolytronX - Business Digitalized @endif
                                    @else
                                        {{ __('Copyright') }} &copy; {{ config('app.name', 'ADC - Amad Diagnostic Centre') }} {{ date('Y') }} <span class="mx-1">|</span> Powered By PolytronX - Business Digitalized
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    @if((isset($admin_settings['enable_cookie']) ? $admin_settings['enable_cookie'] : 'off') == 'on')
        @include('layouts.cookie_consent')
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('custom-scripts')
<script src="{{ asset('js/jquery.js') }}"></script>
<script src="{{ asset('js/custom.js') }}"></script>
<script src="{{ asset('assets/js/plugins/bootstrap.js') }}"></script>
@stack('script')
@stack('scripts')
@if((isset($admin_settings['cust_darklayout']) ? $admin_settings['cust_darklayout'] : 'off') == 'on')
<script>
        "use strict";
       document.addEventListener('DOMContentLoaded', (event) => {
       const recaptcha = document.querySelector('.g-recaptcha');
       recaptcha.setAttribute("data-theme", "dark");
       });
</script>
@endif
</body>
</html>

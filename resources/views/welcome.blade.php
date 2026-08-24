<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ADC - Amad Diagnostic Centre</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <style>
            *{box-sizing:border-box;border-width:0;border-style:solid;border-color:#e5e7eb}html{line-height:1.5;font-family:Figtree,sans-serif}body{margin:0;background:#f9fafb}.bg-dots{background-image:radial-gradient(#e5e7eb 1px,transparent 1px);background-size:16px 16px}
        </style>
    </head>
    <body class="antialiased bg-gray-100">
        <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots bg-center">
            @if (Route::has('login'))
                <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-gray-900">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ml-4 font-semibold text-gray-600 hover:text-gray-900">Register</a>
                        @endif
                    @endauth
                </div>
            @endif

            <div class="max-w-7xl mx-auto p-6 lg:p-8 text-center">
                <div class="flex justify-center mb-8">
                    <div class="h-16 w-16 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-2xl font-bold">ADC</span>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-gray-900">ADC - Amad Diagnostic Centre</h1>
                <p class="mt-4 text-lg text-gray-600">Patient Management & Diagnostic Centre — Appointments, Reports, and Care in one place.</p>

                <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 text-left">
                    <div class="scale-100 p-6 bg-white rounded-lg shadow">
                        <h2 class="text-xl font-semibold text-gray-900">Appointment Booking</h2>
                        <p class="mt-4 text-gray-500 text-sm">Book, reschedule and track appointments with real-time availability and staff coordination.</p>
                    </div>
                    <div class="scale-100 p-6 bg-white rounded-lg shadow">
                        <h2 class="text-xl font-semibold text-gray-900">Patient Management</h2>
                        <p class="mt-4 text-gray-500 text-sm">Centralized patient records, history, and reporting for efficient clinic operations.</p>
                    </div>
                    <div class="scale-100 p-6 bg-white rounded-lg shadow">
                        <h2 class="text-xl font-semibold text-gray-900">Diagnostic Services</h2>
                        <p class="mt-4 text-gray-500 text-sm">Comprehensive diagnostic workflows and report management built for healthcare.</p>
                    </div>
                    <div class="scale-100 p-6 bg-white rounded-lg shadow">
                        <h2 class="text-xl font-semibold text-gray-900">Powered By PolytronX</h2>
                        <p class="mt-4 text-gray-500 text-sm">Business Digitalized — Secure, scalable, and built for growth. Your clinic, digitalized.</p>
                    </div>
                </div>

                <div class="flex justify-center mt-10">
                    <a href="{{ route('login') }}" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Go to Login</a>
                    <a href="{{ url('/dashboard') }}" class="ml-4 px-6 py-3 bg-white border rounded-lg hover:bg-gray-50">Dashboard</a>
                </div>

                <div class="flex justify-center mt-16 text-sm text-gray-500">
                    <div class="text-center">
                        &copy; {{ date('Y') }} ADC - Amad Diagnostic Centre | Powered By PolytronX - Business Digitalized
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>

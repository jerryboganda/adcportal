@extends('layouts.main')
@section('page-title')
{{ __('Dashboard') }}
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/js/plugins/apexcharts.js') }}"></script>
    <script>
        "use strict";
        (function() {
            var chartBarOptions = {
                series: [{
                    name: '{{ __('Order') }}',
                    data: {!! json_encode($chartData['data']) !!},

                }, ],

                chart: {
                    height: 300,
                    type: 'area',
                    dropShadow: {
                        enabled: true,
                        color: '#000',
                        top: 18,
                        left: 7,
                        blur: 10,
                        opacity: 0.2
                    },
                    toolbar: {
                        show: false
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    width: 2,
                    curve: 'smooth'
                },
                title: {
                    text: '',
                    align: 'left'
                },
                xaxis: {
                    categories: {!! json_encode($chartData['label']) !!},
                    title: {
                        text: '{{ __('Months') }}'
                    }
                },
                colors: ['#6fd944', '#6fd944'],

                grid: {
                    strokeDashArray: 4,
                },
                legend: {
                    show: false,
                },
                yaxis: {
                    title: {
                        text: '{{ __('Order') }}'
                    },

                }

            };
            var arChart = new ApexCharts(document.querySelector("#chart-sales"), chartBarOptions);
            arChart.render();
        })();
    </script>
@endpush
@section('content')
<div class="row row-gap mb-4 mt-3 pt-0">
    <div class="col-xxl-6 col-12 dashboard-super-admin-col">
        <div class="row row-gap">
        <div class="col-md-9 col-sm-8 col-12">
            <div class="dashboard-card">
                <img src="{{ asset('assets/images/layer.png')}}" class="dashboard-card-layer" alt="layer">
                <div class="card-inner">
                    <div class="card-content">
                        <h3>{{ __(Auth::user()->name) }}</h3>
                        <p>{{ __('Welcome to your clinic dashboard!') }} </p>
                        <div class="btn-wrp d-flex gap-3">
                            <a href="javascript:" class="btn btn-primary d-flex align-items-center gap-1 cp_link" tabindex="0" data-link="{{ route('start') }}"
                                data-bs-toggle="tooltip" data-bs-placement="top"
                                title="{{ __('Click To Copy Link') }}">
                                <i class="ti ti-link text-white"></i>
                                <span>{{ __('Landing Page')}}</span></a>
                                <a href="javascript:" class="btn btn-primary socialShareButton " tabindex="0" id="socialShareButton" data-bs-toggle="tooltip" data-bs-placement="top"
                                title="{{ __('Click To Share Button') }}">
                                    <i class="ti ti-share text-white"></i>
                                </a>
                            <div id="sharingButtonsContainer" class="sharingButtonsContainer"
                            style="display: none;">
                                <div
                                    class="Demo1 d-flex align-items-center justify-content-center mb-5 hidden">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-icon  d-flex align-items-center justify-content-center">
                    <svg width="74" height="74" viewBox="0 0 74 74" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.1943 52.8056C19.9018 52.8056 19.6231 52.6336 19.5019 52.3473C19.3387 51.9647 19.5173 51.5224 19.8996 51.3597L27.5184 48.1193C27.9014 47.958 28.344 48.1343 28.5057 48.5173C28.6689 48.8999 28.4903 49.3422 28.108 49.5049L20.4892 52.7453C20.3927 52.7861 20.2927 52.8056 20.1943 52.8056Z" fill="url(#paint0_linear_406_10)"/>
                    <path d="M53.7981 52.8056C53.6996 52.8056 53.5997 52.7861 53.5033 52.7454L45.8845 49.505C45.5022 49.3421 45.3235 48.9 45.4867 48.5174C45.6492 48.1344 46.0925 47.9579 46.474 48.1193L54.0928 51.3597C54.4751 51.5226 54.6538 51.9647 54.4906 52.3473C54.3693 52.6336 54.0907 52.8056 53.7981 52.8056Z" fill="url(#paint1_linear_406_10)"/>
                    <path d="M36.9998 33.1779C36.5837 33.1779 36.2471 32.8408 36.2471 32.4251V25.781C36.2471 25.3654 36.5838 25.0283 36.9998 25.0283C37.4157 25.0283 37.7525 25.3654 37.7525 25.781V32.4251C37.7525 32.8408 37.4157 33.1779 36.9998 33.1779Z" fill="url(#paint2_linear_406_10)"/>
                    <path d="M49.5079 43.9468V45.9229C49.5079 46.1843 49.3196 46.4111 49.062 46.4572L46.7784 46.8762C46.5246 48.1872 46.0056 49.4058 45.2867 50.4785L46.6054 52.3892C46.7553 52.6045 46.7284 52.8966 46.5438 53.0812L45.1482 54.4768C44.9599 54.6651 44.6677 54.692 44.4523 54.5421L42.5377 53.2234C41.4689 53.9423 40.2502 54.4576 38.9393 54.7112L38.5203 56.9987C38.4742 57.2563 38.2512 57.4448 37.986 57.4448H36.0137C35.7485 57.4448 35.5255 57.2563 35.4794 56.9987L35.0604 54.7112C33.7494 54.4576 32.5346 53.9423 31.462 53.2234L29.5474 54.5421C29.3321 54.6881 29.04 54.6651 28.8515 54.4768L27.4559 53.0812C27.2713 52.8966 27.2444 52.6045 27.3943 52.3892L28.713 50.4785C27.9941 49.4059 27.4751 48.1872 27.2213 46.8762L24.9377 46.4572C24.6802 46.4111 24.4917 46.1843 24.4917 45.9229V43.9468C24.4917 43.6854 24.6802 43.4586 24.9377 43.4125L27.2213 42.9934C27.4751 41.6825 27.9941 40.4636 28.713 39.3911L27.3943 37.4804C27.2444 37.2652 27.2713 36.9729 27.4559 36.7883L28.8515 35.3928C29.0398 35.2044 29.3321 35.1774 29.5474 35.3274L31.462 36.646C32.5346 35.9271 33.7495 35.412 35.0604 35.1582L35.4794 32.8707C35.5255 32.6131 35.7485 32.4248 36.0137 32.4248H37.986C38.2512 32.4248 38.4742 32.6131 38.5203 32.8707L38.9393 35.1582C40.2504 35.412 41.4691 35.9271 42.5377 36.646L44.4523 35.3274C44.6677 35.1774 44.9598 35.2044 45.1482 35.3928L46.5438 36.7883C46.7284 36.9729 46.7553 37.265 46.6054 37.4804L45.2867 39.3911C46.0056 40.4636 46.5246 41.6825 46.7784 42.9934L49.062 43.4125C49.3196 43.4586 49.5079 43.6855 49.5079 43.9468Z" fill="#18BF6B"/>
                    <path d="M41.5599 44.9349C41.5599 47.454 39.5179 49.4939 36.9989 49.4939C34.4819 49.4939 32.4399 47.4538 32.4399 44.9349C32.4399 42.4178 34.4819 40.376 36.9989 40.376C39.5179 40.376 41.5599 42.4179 41.5599 44.9349Z" fill="url(#paint3_linear_406_10)"/>
                    <path opacity="0.6" d="M37.0015 13.1563C40.1871 13.1563 42.7696 10.5738 42.7696 7.38822C42.7696 4.20258 40.1871 1.62012 37.0015 1.62012C33.8159 1.62012 31.2334 4.20258 31.2334 7.38822C31.2334 10.5738 33.8159 13.1563 37.0015 13.1563Z" fill="#18BF6B"/>
                    <path d="M47.2767 25.7802H26.7232C25.4057 25.7802 24.3601 24.5902 24.6152 23.2981C25.7712 17.5094 30.8799 13.1572 37.0001 13.1572C43.1203 13.1572 48.2289 17.5094 49.3848 23.2981C49.6398 24.5901 48.6028 25.7802 47.2767 25.7802Z" fill="#18BF6B"/>
                    <path opacity="0.6" d="M14.7637 59.7559C17.9493 59.7559 20.5318 57.1735 20.5318 53.9878C20.5318 50.8022 17.9493 48.2197 14.7637 48.2197C11.5781 48.2197 8.99561 50.8022 8.99561 53.9878C8.99561 57.1735 11.5781 59.7559 14.7637 59.7559Z" fill="#18BF6B"/>
                    <path d="M25.0391 72.3798H4.48541C3.16786 72.3798 2.12233 71.1898 2.37742 69.8977C3.53338 64.109 8.64213 59.7568 14.7623 59.7568C20.8825 59.7568 25.9911 64.109 27.147 69.8977C27.4021 71.1898 26.3651 72.3798 25.0391 72.3798Z" fill="#18BF6B"/>
                    <path opacity="0.6" d="M59.2388 59.7559C62.4244 59.7559 65.0069 57.1735 65.0069 53.9878C65.0069 50.8022 62.4244 48.2197 59.2388 48.2197C56.0532 48.2197 53.4707 50.8022 53.4707 53.9878C53.4707 57.1735 56.0532 59.7559 59.2388 59.7559Z" fill="#18BF6B"/>
                    <path d="M69.5142 72.3798H48.9605C47.643 72.3798 46.5974 71.1898 46.8525 69.8977C48.0085 64.109 53.1172 59.7568 59.2374 59.7568C65.3576 59.7568 70.4662 64.109 71.6221 69.8977C71.8772 71.1898 70.8402 72.3798 69.5142 72.3798Z" fill="#18BF6B"/>
                    <defs>
                    <linearGradient id="paint0_linear_406_10" x1="24.0037" y1="52.8056" x2="24.0037" y2="48.0594" gradientUnits="userSpaceOnUse">
                    <stop offset="0.0168" stop-color="#CCCCCC"/>
                    <stop offset="1" stop-color="#F2F2F2"/>
                    </linearGradient>
                    <linearGradient id="paint1_linear_406_10" x1="49.9887" y1="52.8056" x2="49.9887" y2="48.0595" gradientUnits="userSpaceOnUse">
                    <stop offset="0.0168" stop-color="#CCCCCC"/>
                    <stop offset="1" stop-color="#F2F2F2"/>
                    </linearGradient>
                    <linearGradient id="paint2_linear_406_10" x1="36.9998" y1="33.1779" x2="36.9998" y2="25.0282" gradientUnits="userSpaceOnUse">
                    <stop offset="0.0168" stop-color="#CCCCCC"/>
                    <stop offset="1" stop-color="#F2F2F2"/>
                    </linearGradient>
                    <linearGradient id="paint3_linear_406_10" x1="36.9999" y1="49.494" x2="36.9999" y2="40.376" gradientUnits="userSpaceOnUse">
                    <stop offset="0.0168" stop-color="#CCCCCC"/>
                    <stop offset="1" stop-color="#F2F2F2"/>
                    </linearGradient>
                    </defs>
                    </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-12">
            <div class="qr-card">
                <div class="qr-card-inner" style="background-color: rgba(12, 175, 96, 0.3);;">
                    <div class="shareqrcode">
                        @if (false)
                        @include('landing-page::layouts.dash_qr_scripts')
                        @endif
                    </div>
                    <div class="qr-card-content">
                        <div class="qr-btn">
                            <span>{{ __('Landing Page')}}</span>
                            <h3 id="greetings" style="display: none;"></h3>
                            <a href="#" class="cp_link" tabindex="0"  data-link="{{ route('start') }}" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Click To Copy Link">
                                <i class="ti ti-layers-linked text-primary"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <div class="col-xxl-6 col-12 dashboard-super-admin-col">
            <div class="row dashboard-wrp">
                <div class="col-sm-4 col-12">
                    <div class="dashboard-project-card">
                        <div class="card-inner  d-flex justify-content-between">
                            <div class="card-content">
                                <div class="theme-avtar bg-white">
                                    <i class="ti ti-users text-danger"></i>
                                </div>
                                <h3 class="mt-3 admin-card-title mb-0 text-danger">{{ __('Total Users') }}</h3>
                                <h4 class="mt-3 mb-0 text-danger">{{ __('Paid Users') }}</h4>
                                <h4 class="mt-3 mb-0 text-danger"><span> {{ $user['total_paid_user'] }} </span></h4>
                            </div>
                            <h3 class="mb-0">{{ $user->total_user }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-12">
                    <div class="dashboard-project-card">
                        <div class="card-inner  d-flex justify-content-between">
                            <div class="card-content">
                                <div class="theme-avtar bg-white">
                                    <i class="ti ti-shopping-cart"></i>
                                </div>
                                <h3 class="mt-3 admin-card-title mb-0">{{ __('Total Orders') }}</h3>
                                <h4 class="mt-2 mb-0">{{ __('Order Amount') }}</h4>
                                <h4 class="mt-2 mb-0"><span>{{super_currency_format_with_sym($user['total_orders_price']) }}</span></h4>
                            </div>
                            <h3 class="mb-0">{{ $user->total_orders }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-12">
                    <div class="dashboard-project-card">
                        <div class="card-inner  d-flex justify-content-between">
                            <div class="card-content">
                                <div class="theme-avtar bg-white">
                                    <i class="ti ti-trophy"></i>
                                </div>
                                <h3 class="mt-3 admin-card-title  mb-0">{{ __('Total Plans') }}</h3>
                                <h4 class="mt-2 mb-0">{{ __('Popular Plan') }}</h4>
                                <h4 class="mt-2 mb-0"><span> {{ !empty($user->popular_plan) ? $user->popular_plan->name : '' }}</span> </h4>
                            </div>
                            <h3 class="mb-0">{{ $user->total_plans }}</h3>
                        </div>
                    </div>
                </div>
            </div>
    </div>
</div>
<div class="row bookinggo-raw">
    <div class="col-12">
        <h4 class="h4 font-weight-400 mb-3">{{ __('Recent Order') }}</h4>
        <div class="card">
            <div class="chart">
                <div id="chart-sales" data-color="primary" data-height="280" class="p-3"></div>
            </div>
        </div>
    </div>
</div>
@endsection

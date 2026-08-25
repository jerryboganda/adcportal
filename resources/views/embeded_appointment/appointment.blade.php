<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Appointment</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>
        @import url('https://fonts.googleapis.com/css?family=Roboto');
        body {
            font-family: 'Roboto', sans-serif;
            
        }
        .booked-success-sec{
            padding: 150px 0 60px;
            background-image: linear-gradient(to top, rgb(40 167 69 / 23%) 0%, rgb(40 167 69 / 16%) 100%);
            height: 100vh;
        }
        .alert-success {
            background-color: #d1e7dd;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            padding: 10px;
        }
        .alert-success svg path{
            fill: #0f5132;
        }
        
    </style>

    {{-- custom-css --}}
    <style type="text/css">
        {!! preg_replace('/<\/?(script|style)[^>]*>/i', '', htmlspecialchars_decode($customCss)) !!}
    </style>
</head>
<body class="theme-1">
    <section class="booked-success-sec">
        <div class="container">
            <div class="row d-flex justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                                <div class="d-flex align-items-center gap-3 justify-content-center flex-column">
                                <div class="alert-success">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"/></svg>
                                </div>
                                {{ __('Study booked successfully! Your study number is') }} {{ $appointment_number }}
                            </div>

                            @if(!empty($study_prep) || !empty($study_screening))
                                <hr>
                                <div class="radiology-instructions" style="text-align:left;">
                                    @if(!empty($study_screening))
                                        <div style="background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:12px 16px;margin-bottom:10px;">
                                            <strong>&#128737; {{ __('Safety screening required') }}</strong> —
                                            {{ __('a short questionnaire will be completed at the clinic before your scan. Please arrive 15 minutes early.') }}
                                        </div>
                                    @endif
                                    @if(!empty($study_prep))
                                        <div style="background:#f0f7ff;border-left:4px solid #0080b6;border-radius:8px;padding:12px 16px;margin-bottom:10px;">
                                            <strong>&#9888; {{ __('Preparation instructions') }}:</strong><br>{{ $study_prep }}
                                        </div>
                                    @endif
                                    @if(!empty($study_contrast) && $study_contrast !== 'none')
                                        <div style="font-size:.95em;color:#334155;">
                                            <strong>{{ __('Contrast') }}:</strong> {{ __('this study may use :type contrast.', ['type' => str_replace('_', ' ', $study_contrast)]) }}
                                        </div>
                                    @endif
                                    @if(!empty($study_tat))
                                        <div style="font-size:.95em;color:#334155;margin-top:6px;">
                                            &#9202; {{ __('Report usually ready within approximately :hours hours.', ['hours' => $study_tat]) }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="text-center mt-3">
                                <a href="{{ route('appointments.form') }}" class="btn btn-primary">{{ __('Return To Appointment') }}</a>
                            </div>
                        </div>  
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- custom-js --}}
    <script type="text/javascript">
        {!! str_replace('</script>', '<\/script>', htmlspecialchars_decode($customJs)) !!}
    </script>
</body>

</html>
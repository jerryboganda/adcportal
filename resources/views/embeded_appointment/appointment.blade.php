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
        {{ htmlspecialchars_decode($customCss) }}
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
                                {{ __('Appointment booked successfully! Your appointment number is') }} {{ $appointment_number }}
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
        {!! htmlspecialchars_decode($customJs) !!}
    </script>
</body>

</html>
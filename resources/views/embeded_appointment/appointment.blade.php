<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Booking Confirmed') }} — {{ $appointment_number }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <style>
        .confirm-hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 48px 16px;
            background: linear-gradient(to bottom, var(--adc-primary-soft), transparent 70%);
        }
        .confirm-card {
            max-width: 640px;
            margin-inline: auto;
            background: var(--adc-surface);
            border: 1px solid var(--adc-border);
            border-radius: var(--adc-radius-lg);
            box-shadow: var(--adc-shadow);
        }
        .confirm-check {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: var(--adc-success-soft);
            color: var(--adc-success-600);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .study-number {
            font-size: var(--adc-text-3xl);
            font-weight: var(--adc-weight-bold);
            letter-spacing: .04em;
            color: var(--adc-primary-700);
        }
        [dir="rtl"] .ur-line { display: block; }
        .ur-block {
            direction: rtl;
            font-family: var(--adc-font-ar), var(--adc-font);
            color: var(--adc-text-muted);
            margin-top: 4px;
        }
        .instr-card {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 12px 16px;
            border-radius: var(--adc-radius-sm);
            margin-bottom: 10px;
            text-align: start;
        }
        .instr-screening { background: #fff7ed; border: 1px solid #fdba74; }
        .instr-prep      { background: var(--adc-info-soft); border-left: 4px solid var(--adc-info); }
        .instr-meta      { background: transparent; border: 1px dashed var(--adc-border); }
        @media print {
            .no-print { display: none !important; }
            .confirm-hero { min-height: auto; padding: 0; }
            .confirm-card { box-shadow: none; border: none; }
        }
    </style>

    {{-- custom-css --}}
    <style type="text/css">
        {!! preg_replace('/<\/?(script|style)[^>]*>/i', '', htmlspecialchars_decode($customCss)) !!}
    </style>
</head>
<body>
    <section class="confirm-hero">
        <div class="container">
            <div class="confirm-card p-4 p-md-5 text-center">
                <div class="confirm-check mb-3" aria-hidden="true">
                    <i class="ti ti-check fs-2"></i>
                </div>

                <h1 class="h4 fw-bold mb-1">{{ __('Appointment confirmed!') }}</h1>
                <p class="text-muted mb-4">دائیگناسٹک اپائنٹمنٹ کامیابی سے بک ہو گیا ہے</p>

                <p class="mb-0 small text-muted">{{ __('Your study number is') }}</p>
                <div class="d-inline-flex align-items-center gap-2 my-2">
                    <span class="study-number">{{ $appointment_number }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary no-print"
                        onclick="navigator.clipboard.writeText('{{ $appointment_number }}').then(() => window.adcToast && adcToast('success','Copied'))"
                        aria-label="{{ __('Copy booking number') }}">
                        <i class="ti ti-copy" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="ur-block mb-4">آپ کا اسٹڈی نمبر: {{ $appointment_number }}</p>

                <div class="radiology-instructions text-start">
                    @if(!empty($study_screening))
                        <div class="instr-card instr-screening">
                            <i class="ti ti-shield-check fs-4 mt-1" aria-hidden="true"></i>
                            <div>
                                <strong>{{ __('Safety screening required') }}</strong> —
                                {{ __('a short questionnaire will be completed at the clinic before your scan. Please arrive 15 minutes early.') }}
                                <div class="ur-block">سیفٹی اسکریننگ ضروری ہے — اسکین سے پہلے کلینک میں ایک مختصر سوالنامہ بھرا جائے گا۔ براہ کرم 15 منٹ پہلے تشریف لائیں۔</div>
                            </div>
                        </div>
                    @endif
                    @if(!empty($study_prep))
                        <div class="instr-card instr-prep">
                            <i class="ti ti-alert-triangle fs-4 mt-1" aria-hidden="true"></i>
                            <div>
                                <strong>{{ __('Preparation instructions') }}:</strong><br>{{ $study_prep }}
                                <div class="ur-block">تیاری کی ہدایات دیکھیے (اوپر انگریزی میں درج ہیں)۔</div>
                            </div>
                        </div>
                    @endif
                    @if(!empty($study_contrast) && $study_contrast !== 'none')
                        <div class="instr-card instr-meta">
                            <i class="ti ti-droplet fs-4 mt-1" aria-hidden="true"></i>
                            <div>
                                <strong>{{ __('Contrast') }}:</strong> {{ __('this study may use :type contrast.', ['type' => str_replace('_', ' ', $study_contrast)]) }}
                            </div>
                        </div>
                    @endif
                    @if(!empty($study_tat))
                        <div class="instr-card instr-meta">
                            <i class="ti ti-clock fs-4 mt-1" aria-hidden="true"></i>
                            <div>{{ __('Report usually ready within approximately :hours hours.', ['hours' => $study_tat]) }}
                                <div class="ur-block">رپورٹ عام طور پر تقریباً {{ $study_tat }} گھنٹوں میں تیار ہو جاتی ہے۔</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-center mt-4 no-print">
                    <a href="{{ route('find.appointment') }}" class="btn btn-primary">
                        <i class="ti ti-search me-1" aria-hidden="true"></i>{{ __('Track your appointment') }}
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="ti ti-printer me-1" aria-hidden="true"></i>{{ __('Print details') }}
                    </button>
                    <a href="{{ route('appointments.form') }}" class="btn btn-outline-primary">
                        {{ __('Return To Appointment') }}
                    </a>
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

@php
    $business = \App\Models\Business::where('slug', request()->route()->parameters['businessSlug'])->first();
    $company_settings = getCompanyAllSetting($business->created_by, $business->id);
    $favicon = isset($company_settings['favicon']) ? $company_settings['favicon'] : (isset($admin_settings['favicon']) ? $admin_settings['favicon'] : 'uploads/logo/favicon.png');
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Appointment Tracking') }} - {{ $business->name ?? 'ADC' }}</title>
    <link rel="icon"
        href="{{ check_file($favicon) ? get_file($favicon) : get_file('uploads/logo/favicon.png') }}{{ '?' . time() }}"
        type="image/x-icon" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #4F46E5;
            --primary-light: #EEF2FF;
            --secondary: #64748B;
            --success: #10B981;
            --warning: #F59E0B;
            --bg-body: #F8FAFC;
            --card-bg: #FFFFFF;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: #1E293B;
            overflow-x: hidden;
        }

        .header-bg {
            background: linear-gradient(135deg, #4F46E5 0%, #312E81 100%);
            padding: 80px 0 120px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .header-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .main-container {
            margin-top: -80px;
            padding-bottom: 50px;
        }

        .tracking-card {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .card-header-c {
            padding: 24px;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header-c h5 {
            margin: 0;
            font-weight: 700;
            color: #0F172A;
        }

        .card-body-c {
            padding: 24px;
        }

        /* Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 20px;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 3px;
            background: #E2E8F0;
            z-index: 0;
        }

        .status-item {
            position: relative;
            z-index: 1;
            text-align: center;
            width: 100%;
        }

        .status-circle {
            width: 32px;
            height: 32px;
            background: #fff;
            border: 3px solid #E2E8F0;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .status-item.active .status-circle {
            border-color: var(--primary);
            background: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .status-item.active .status-circle i {
            color: white;
        }

        .status-item.completed .status-circle {
            border-color: var(--success);
            background: var(--success);
        }

        .status-item.completed .status-circle i {
            color: white;
        }

        .status-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-item.active .status-label {
            color: var(--primary);
        }

        .info-label {
            font-size: 0.8rem;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1E293B;
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .file-item:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .file-icon {
            width: 48px;
            height: 48px;
            background: #E0E7FF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
            margin-right: 16px;
        }

        .btn-new-search {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-new-search:hover {
            background: white;
            color: var(--primary);
        }
    </style>
</head>

<body>

    <div class="header-bg">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1">{{ __('Tracking Portal') }}</h1>
                    <p class="opacity-75 mb-3">{{ $business->name ?? '' }}</p>
                    <a href="{{ route('find.appointment', $business->slug) }}" class="btn-new-search btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> {{ __('Start New Search') }}
                    </a>
                </div>
                <div class="bg-white p-2 rounded-3 shadow-sm d-inline-block">
                    {!! QrCode::size(120)->generate(url()->current()) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="container main-container">

        <!-- Status Card -->
        <div class="tracking-card">
            <div class="card-body-c">
                <div class="text-center mb-4">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold">
                        {{ __('Appointment ID') }}:
                        #{{ App\Models\Appointment::appointmentNumberWithFormat($appointmentDetails->id, $company_settings) }}
                    </span>
                </div>

                <div class="status-timeline">
                    @php
                        // Step 1: Booking (Always Done)
                        $step1_state = 'completed';

                        // Step 2: Payment
                        $step2_state = 'pending';
                        $step2_label = __('Pending');
                        if (!empty($appointmentDetails->payment)) {
                            $step2_state = 'completed';
                            $step2_label = __('Paid');
                        }

                        // Step 3: Booking Status — derived from the clinical pipeline (StudyState),
                        // never from English substring matching of legacy status titles.
                        $currentStatus = $appointmentDetails->StatusData->title ?? __('Pending');
                        $step3_state = 'active';
                        $trackingState = \App\Enums\StudyState::tryFrom((string) ($appointmentDetails->workflow_state ?? ''));
                        if ($trackingState) {
                            $currentStatus = $trackingState->label();
                            if (in_array($trackingState, [\App\Enums\StudyState::Reported, \App\Enums\StudyState::Delivered], true)) {
                                $step3_state = 'completed';
                            }
                        } else {
                            foreach (['Complete', 'Done', 'Paid', 'Confirmed', 'Approved', 'Succeed'] as $status) {
                                if (stripos($currentStatus, $status) !== false) {
                                    $step3_state = 'completed';
                                    break;
                                }
                            }
                        }

                        // Step 4: Report
                        $step4_state = 'pending';
                        $step4_label = __('Waiting');
                        $reportCount = $appointmentDetails->reports ? $appointmentDetails->reports->count() : 0;
                        if ($reportCount > 0) {
                            $step4_state = 'completed'; // or active-success
                            $step4_label = __('Available');
                        }
                    @endphp

                    <!-- Step 1 -->
                    <div class="status-item {{ $step1_state }}">
                        <div class="status-circle">
                            <i class="ti ti-calendar"></i>
                        </div>
                        <div class="status-label">{{ __('Booking') }}</div>
                        <div class="small text-muted">{{ __('Confirmed') }}</div>
                    </div>

                    <!-- Step 2 -->
                    <div class="status-item {{ $step2_state }}">
                        <div class="status-circle">
                            <i class="ti ti-credit-card"></i>
                        </div>
                        <div class="status-label">{{ __('Payment') }}</div>
                        <div class="small text-muted">{{ $step2_label }}</div>
                    </div>

                    <!-- Step 3 -->
                    <div class="status-item {{ $step3_state }}">
                        <div class="status-circle">
                            <i class="ti ti-stethoscope"></i>
                        </div>
                        <div class="status-label">{{ __('Status') }}</div>
                        <div class="small text-muted">{{ $currentStatus }}</div>
                    </div>

                    <!-- Step 4 -->
                    <div class="status-item {{ $step4_state }}">
                        <div class="status-circle">
                            <i class="ti ti-file-report"></i>
                        </div>
                        <div class="status-label">{{ __('Report') }}</div>
                        <div class="small text-muted">{{ $step4_label }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="tracking-card h-100">
                    <div class="card-header-c">
                        <h5><i class="ti ti-info-circle me-2 text-primary"></i>{{ __('Appointment Details') }}</h5>
                    </div>
                    <div class="card-body-c">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="info-label">{{ __('Service Required') }}</div>
                                <div class="info-value">
                                    {{ !empty($appointmentDetails->ServiceData) ? $appointmentDetails->ServiceData->name : '-' }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">{{ __('Assigned Staff') }}</div>
                                <div class="info-value">
                                    @if(!empty($appointmentDetails->StaffData))
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle p-1 me-2"
                                                style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;">
                                                <i class="ti ti-user text-primary" style="font-size:14px;"></i>
                                            </div>
                                            {{ $appointmentDetails->StaffData->name }}
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle p-1 me-2"
                                                style="width:30px;height:30px;display:flex;align-items:center;justify-content:center;">
                                            <i class="ti ti-user text-primary" style="font-size:14px;"></i>
                                        </div>
                                        {{ __('To be assigned') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">{{ __('Date') }}</div>
                                <div class="info-value"><i
                                        class="ti ti-calendar me-1 opacity-50"></i>{{ $appointmentDetails->date }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">{{ __('Time Interval') }}</div>
                                <div class="info-value"><i
                                        class="ti ti-clock me-1 opacity-50"></i>{{ $appointmentDetails->time }}</div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">{{ __('Location') }}</div>
                                <div class="info-value">
                                    {{ !empty($appointmentDetails->LocationData) ? $appointmentDetails->LocationData->name : '-' }}
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="text-light">
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">{{ __('Patient Name') }}</div>
                                <div class="info-value">
                                    {{ !empty($appointmentDetails->CustomerData) ? $appointmentDetails->CustomerData->name : $appointmentDetails->name ?? 'Guest' }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">{{ __('Contact Info') }}</div>
                                <div class="info-value">
                                    {{ !empty($appointmentDetails->CustomerData) ? $appointmentDetails->CustomerData->customer->mobile_no : $appointmentDetails->contact }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Documents -->
                <div class="tracking-card">
                    <div class="card-header-c">
                        <h5><i class="ti ti-files me-2 text-primary"></i>{{ __('Documents') }}</h5>
                        <span class="badge bg-light text-primary">{{ $appointmentDetails->reports->count() }}</span>
                    </div>
                    <div class="card-body-c">
                        @if($appointmentDetails->reports && $appointmentDetails->reports->count() > 0)
                            @foreach($appointmentDetails->reports as $report)
                                <a href="{{ route('appointment.reports.download', $report->id) }}" class="file-item">
                                    <div class="file-icon">
                                        <i class="ti ti-file-analytics"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="text-truncate fw-bold text-dark">{{ $report->file_name }}</div>
                                        <div class="small text-muted">{{ $report->file_size_formatted ?? 'PDF Document' }}</div>
                                    </div>
                                    <div class="ms-auto text-primary">
                                        <i class="ti ti-download"></i>
                                    </div>
                                </a>
                            @endforeach
                        @else
                            <div class="text-center py-4">
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486777.png" width="60"
                                    class="mb-3 opacity-25" alt="Empty">
                                <p class="text-muted small mb-0">{{ __('No medical reports available yet.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment -->
                <div class="tracking-card">
                    <div class="card-header-c bg-light-subtle">
                        <h5><i class="ti ti-receipt me-2 text-success"></i>{{ __('Payment Summary') }}</h5>
                    </div>
                    <div class="card-body-c">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ __('Subtotal') }}</span>
                            <span class="fw-bold">
                                {{ !empty($appointmentDetails->payment) ? currency_format_with_sym($appointmentDetails->payment->amount, $business['created_by'], $business->id) :
    ((false) && !empty($appointmentDetails->payments($appointmentDetails->id)) ?
        currency_format_with_sym($appointmentDetails->payments($appointmentDetails->id)->amount, $business['created_by'], $business->id) :
        currency_format_with_sym(0, $business['created_by'], $business->id)) }}
                            </span>
                        </div>
                        @if (false && !empty($appointmentDetails->payment->coupon_amount))
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>{{ __('Discount') }}</span>
                                <span>-{{ currency_format_with_sym($appointmentDetails->payment->coupon_amount, $business['created_by'], $business->id) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between pt-3 mt-3 border-top">
                            <span class="h6 mb-0">{{ __('Total Paid') }}</span>
                            <span class="h5 mb-0 text-primary fw-bold">
                                {{ !empty($appointmentDetails->payment) ? currency_format_with_sym($appointmentDetails->payment->final_amount ?? $appointmentDetails->payment->amount, $business['created_by'], $business->id) : currency_format_with_sym(0, $business['created_by'], $business->id) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 text-muted small">
            &copy; {{ date('Y') }} {{ $business->name ?? 'Amad Diagnostic Centre' }}. {{ __('All rights reserved.') }}
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
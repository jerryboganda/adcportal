@extends('form_layout.layout')

@push('css')
<style>
    /* Hide default body styling from layout */
    body.Formlayout11-v1 {
        background: linear-gradient(135deg, #e8eef5 0%, #dce4f2 50%, #e8dff0 100%) !important;
    }
    
    /* Success page styling */
    .fl11-success-content {
        text-align: center;
        padding: 30px 20px;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .fl11-success-icon {
        width: 80px;
        height: 80px;
        background: #1ec49d;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
    }
    
    .fl11-success-icon svg {
        width: 50px;
        height: 50px;
        stroke: white;
        stroke-width: 3;
    }
    
    .fl11-success-content h1 {
        font-size: 32px;
        font-weight: 700;
        color: #1a2332;
        margin-bottom: 10px;
    }
    
    .fl11-success-content > p {
        font-size: 16px;
        color: #6b7280;
        margin-bottom: 30px;
    }
    
    /* Booking details table */
    .fl11-booking-details {
        background: transparent;
        padding: 0;
        margin: 0 auto 30px;
        text-align: left;
        max-width: 450px;
    }
    
    .fl11-booking-details .fl11-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px 0;
        border-bottom: none;
    }
    
    .fl11-booking-details .fl11-detail-label {
        font-size: 16px;
        font-weight: 600;
        color: #1a2332;
        flex-shrink: 0;
        min-width: 140px;
    }
    
    .fl11-booking-details .fl11-detail-value {
        font-size: 16px;
        color: #1ec49d;
        font-weight: 500;
        text-align: right;
        word-break: break-word;
    }
    
    /* QR Code section */
    .fl11-qr-section {
        margin: 30px 0;
        text-align: center;
    }
    
    .fl11-qr-section h4 {
        font-size: 16px;
        font-weight: 600;
        color: #1a2332;
        margin-bottom: 15px;
    }
    
    .fl11-qr-code {
        display: inline-block;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .fl11-qr-code img,
    .fl11-qr-code canvas {
        max-width: 150px;
        max-height: 150px;
        display: block;
        margin: 0 auto;
    }

    .fl11-services-grid { 
        /* Removed to allow main-style.css and responsive.css to handle this */
    }
</style>
@endpush

@section('form_content')
<div class="fl11-booking-container">
    <!-- Left Sidebar -->
    <div class="fl11-sidebar">
        <div class="fl11-sidebar-header">
            <h2 class="fl11-bilingual">
                <span>{{ __('Book Appointment:') }}</span>
                <span class="fl11-ur" style="font-size: 14px; opacity: 0.9;">اپائنٹمنٹ بک کریں</span>
            </h2>
            <h3 id="fl11-step-title" class="fl11-bilingual">
                <span>{{ __('Service Selection') }}</span>
                <span class="fl11-ur" style="font-size: 14px; color: #00e5cc;">سروس کا انتخاب</span>
            </h3>
        </div>
        
        <div class="fl11-steps">
            <div class="fl11-step active" data-step="1">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Service') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">سروس</span>
                </div>
            </div>
            <div class="fl11-step" data-step="2">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Pick a Time') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">وقت کا انتخاب</span>
                </div>
            </div>
            <div class="fl11-step" data-step="3">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Additional Details') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">مزید معلومات</span>
                </div>
            </div>
            <div class="fl11-step" data-step="4">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Share Your Details') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">آپ کی تفصیلات</span>
                </div>
            </div>
            <div class="fl11-step" data-step="5">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Payment') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">ادائیگی</span>
                </div>
            </div>
            <div class="fl11-step" data-step="6">
                <div class="fl11-step-indicator">
                    <div class="fl11-step-dot"></div>
                </div>
                <div class="fl11-bilingual">
                    <span class="fl11-step-label">{{ __('Done') }}</span>
                    <span class="fl11-ur" style="font-size: 10px; opacity: 0.8;">مکمل</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="fl11-main-content">
        {{ Form::open(['url' => '#', 'method' => 'post', 'id' => 'appointment-form', 'class' => 'appointment-forms', 'enctype' => 'multipart/form-data']) }}
        <input type="hidden" name="business_id" value="{{ $business->id }}">
        <input type="hidden" name="payment" value="manually">
        
        <!-- STEP 1: Service Selection -->
        <div class="fl11-step-content fl11-step-1 active" id="fl11Step1">
            <div class="fl11-content-header">
                <h1 class="fl11-bilingual">
                    <span>{{ __('Please Select Services:') }}</span>
                    <span class="fl11-ur" style="color: #64748b; font-size: 16px;">براہ کرم سروس کا انتخاب کریں</span>
                </h1>
            </div>

            <div class="fl11-content-body">
                <div class="fl11-form-section">
                    <div class="fl11-form-group">
                        <label class="fl11-bilingual">
                            <span>{{ __('Choose a category') }} <span class="fl11-required">*</span></span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">درجہ بندی کا انتخاب کریں</span>
                        </label>
                        <div class="fl11-select-wrapper">
                            <select name="category" id="categorySelect" class="fl11-select" required>
                                <option value="">{{ __('Select category first') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">
                                        @if(stripos($category->name, 'X-ray') !== false || stripos($category->name, 'Xray') !== false)
                                            🩻 {{ $category->name }} (ایکس رے)
                                        @elseif(stripos($category->name, 'Ultrasound') !== false)
                                            🔊 {{ $category->name }} (الٹراساؤنڈ)
                                        @elseif(stripos($category->name, 'CT Scan') !== false || stripos($category->name, 'CT-Scan') !== false)
                                            💿 {{ $category->name }} (سی ٹی اسکین)
                                        @elseif(stripos($category->name, 'MRI') !== false)
                                            🧲 {{ $category->name }} (ایم آئی آئی)
                                        @else
                                            🏥 {{ $category->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                    </div>

                    <div class="fl11-form-group">
                        <label class="fl11-bilingual">
                            <span>{{ __('Choose a service') }} <span class="fl11-required">*</span></span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">سروس کا انتخاب کریں</span>
                        </label>
                        <div class="fl11-select-wrapper">
                            <select name="service" id="serviceSelect" class="fl11-select" required>
                                <option value="">{{ __('Select service') }}</option>
                            </select>
                            <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                        @stack('quantity')
                        @stack('no_of_person')
                        @stack('service_duration')
                    </div>

                    <div class="fl11-form-group">
                        <label class="fl11-bilingual">
                            <span>{{ __('Choose a location') }}</span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">جگہ کا انتخاب کریں</span>
                        </label>
                        <div class="fl11-select-wrapper">
                            <select name="location" id="locationSelect" class="fl11-select">
                                <option value="">{{ __('Select location') }}</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                    </div>

                    <div class="fl11-form-group">
                        <label class="fl11-bilingual">
                            <span>{{ __('Staff') }}</span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">عملہ</span>
                        </label>
                        <div class="fl11-select-wrapper">
                            <select name="staff" id="staffSelect" class="fl11-select">
                                <option value="">{{ __('Select staff') }}</option>
                            </select>
                            <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6,9 12,15 18,9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Summary Panel -->
                <div class="fl11-summary-panel">
                    <div class="fl11-summary-header">
                        <h4 class="fl11-bilingual">
                            <span>{{ __('Summary') }}</span>
                            <span class="fl11-ur" style="font-size: 14px; color: #00e5cc;">خلاصہ</span>
                        </h4>
                        <svg class="fl11-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="9"></line>
                            <line x1="9" y1="13" x2="15" y2="13"></line>
                            <line x1="9" y1="17" x2="12" y2="17"></line>
                        </svg>
                    </div>
                    <div class="fl11-summary-content">
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Selected Category:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">منتخب درجہ بندی</span>
                            </div>
                            <span class="fl11-summary-value" id="summaryCategory">{{ __('None yet') }}</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Selected Service:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">منتخب سروس</span>
                            </div>
                            <span class="fl11-summary-value" id="summaryService">{{ __('None yet') }}</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Cost estimate:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">تخمینہ لاگت</span>
                            </div>
                            <span class="fl11-summary-value fl11-cost" id="summaryCost">{{ $currency_symbol ?? '$' }}0</span>
                            <span class="fl11-summary-sub" id="summaryCostNote">{{ __('None yet') }}</span>
                        </div>
                        @stack('calculate_tax')
                        @stack('flexible_price')
                        @stack('sequential_price')
                        @stack('calculate_discount')
                    </div>
                </div>
            </div>

            <!-- Category Cards Grid -->
            <div class="fl11-services-grid">
                @php
                    $colors = ['teal', 'blue', 'purple', 'pink'];
                    $colorCodes = [
                        'teal' => '#00BFA5',
                        'blue' => '#2196F3', 
                        'purple' => '#9C27B0',
                        'pink' => '#E91E63'
                    ];
                @endphp
                @foreach ($categories as $index => $category)
                    @php
                        $color = $colors[$index % 4];
                        $colorCode = $colorCodes[$color];
                        $isUrdu = stripos($category->name, 'X-ray') !== false || stripos($category->name, 'Xray') !== false ? 'ایکس رے' : 
                                 (stripos($category->name, 'Ultrasound') !== false ? 'الٹراساؤنڈ' : 
                                 (stripos($category->name, 'CT Scan') !== false || stripos($category->name, 'CT-Scan') !== false ? 'سی ٹی اسکین' : 
                                 (stripos($category->name, 'OPG') !== false ? 'او پی جی' : 'درجہ بندی')));
                    @endphp
                    <div class="fl11-service-card fl11-neon-card" data-color="{{ $color }}" data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}">
                        <div class="fl11-card-checkbox">
                            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                <polyline points="20,6 9,17 4,12"></polyline>
                            </svg>
                        </div>
                        <div class="fl11-card-icon fl11-icon-glow">
                            @if(stripos($category->name, 'X-ray') !== false || stripos($category->name, 'Xray') !== false)
                                <!-- X-ray Icon -->
                                <svg viewBox="0 0 64 64" fill="none" class="fl11-category-svg">
                                    <rect x="20" y="12" width="24" height="40" stroke="{{ $colorCode }}" stroke-width="2.5" rx="2" fill="none"/>
                                    <line x1="26" y1="18" x2="38" y2="18" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <line x1="26" y1="24" x2="38" y2="24" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <circle cx="32" cy="35" r="6" stroke="{{ $colorCode }}" stroke-width="2.5" fill="none"/>
                                    <line x1="27" y1="30" x2="37" y2="40" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <line x1="37" y1="30" x2="27" y2="40" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <line x1="26" y1="46" x2="38" y2="46" stroke="{{ $colorCode }}" stroke-width="2"/>
                                </svg>
                            @elseif(stripos($category->name, 'Ultrasound') !== false)
                                <!-- Ultrasound Probe Icon -->
                                <svg viewBox="0 0 64 64" fill="none" class="fl11-category-svg">
                                    <rect x="28" y="10" width="8" height="18" rx="2" stroke="{{ $colorCode }}" stroke-width="2.5"/>
                                    <line x1="28" y1="16" x2="36" y2="16" stroke="{{ $colorCode }}" stroke-width="1.5"/>
                                    <line x1="28" y1="20" x2="36" y2="20" stroke="{{ $colorCode }}" stroke-width="1.5"/>
                                    <path d="M26 28 L26 35 Q26 40, 32 42 Q38 40, 38 35 L38 28 Z" stroke="{{ $colorCode }}" stroke-width="2.5" fill="none"/>
                                    <path d="M32 42 L32 46" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <path d="M28 48 L36 48" stroke="{{ $colorCode }}" stroke-width="2" opacity="0.8"/>
                                    <path d="M24 50 L40 50" stroke="{{ $colorCode }}" stroke-width="2" opacity="0.6"/>
                                    <path d="M20 52 L44 52" stroke="{{ $colorCode }}" stroke-width="2" opacity="0.4"/>
                                </svg>
                            @elseif(stripos($category->name, 'CT Scan') !== false || stripos($category->name, 'CT-Scan') !== false)
                                <!-- CT Scanner Machine Icon -->
                                <svg viewBox="0 0 64 64" fill="none" class="fl11-category-svg">
                                    <circle cx="32" cy="32" r="20" stroke="{{ $colorCode }}" stroke-width="3"/>
                                    <circle cx="32" cy="32" r="14" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <rect x="26" y="30" width="12" height="20" stroke="{{ $colorCode }}" stroke-width="2" rx="1"/>
                                    <line x1="26" y1="35" x2="38" y2="35" stroke="{{ $colorCode }}" stroke-width="1.5"/>
                                    <line x1="32" y1="12" x2="32" y2="18" stroke="{{ $colorCode }}" stroke-width="2.5" opacity="0.7"/>
                                    <line x1="32" y1="46" x2="32" y2="52" stroke="{{ $colorCode }}" stroke-width="2.5" opacity="0.7"/>
                                    <line x1="12" y1="32" x2="18" y2="32" stroke="{{ $colorCode }}" stroke-width="2.5" opacity="0.7"/>
                                    <line x1="46" y1="32" x2="52" y2="32" stroke="{{ $colorCode }}" stroke-width="2.5" opacity="0.7"/>
                                </svg>
                            @elseif(stripos($category->name, 'OPG') !== false)
                                <!-- OPG Dental Panoramic Icon -->
                                <svg viewBox="0 0 64 64" fill="none" class="fl11-category-svg">
                                    <path d="M12 20 Q32 8 52 20" stroke="{{ $colorCode }}" stroke-width="3" fill="none"/>
                                    <path d="M12 44 Q32 56 52 44" stroke="{{ $colorCode }}" stroke-width="3" fill="none"/>
                                    <rect x="20" y="22" width="6" height="10" rx="2" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <rect x="29" y="20" width="6" height="12" rx="2" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <rect x="38" y="22" width="6" height="10" rx="2" stroke="{{ $colorCode }}" stroke-width="2"/>
                                </svg>
                            @else
                                <!-- Default Medical Icon -->
                                <svg viewBox="0 0 64 64" fill="none" class="fl11-category-svg">
                                    <circle cx="32" cy="32" r="20" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <circle cx="32" cy="32" r="12" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <path d="M32 12 L32 8" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <path d="M32 56 L32 52" stroke="{{ $colorCode }}" stroke-width="2"/>
                                    <ellipse cx="32" cy="32" rx="6" ry="16" stroke="{{ $colorCode }}" stroke-width="2"/>
                                </svg>
                            @endif
                        </div>
                        <div class="fl11-bilingual" style="align-items: center;">
                            <h5 class="fl11-card-title">{{ $category->name }}</h5>
                            <span class="fl11-ur" style="font-size: 11px; opacity: 0.8; color: {{ $colorCode }};">{{ $isUrdu }}</span>
                        </div>
                        <p class="fl11-card-description">{{ Str::limit($category->description ?? __('Select this category to view available services'), 80) }}</p>
                    </div>
                @endforeach
            </div>

            @stack('add_services')
            @stack('shopping_cart_icon')

            <!-- Continue Button -->
            <div class="fl11-button-group">
                <button type="button" class="fl11-continue-btn" id="fl11ContinueStep1">
                    <div class="fl11-bilingual" style="align-items: center; justify-content: center;">
                        <span>{{ __('Continue to Time Selection') }} →</span>
                        <span class="fl11-ur" style="font-size: 13px; margin-top: 0;">وقت کے انتخاب کے لیے جاری رکھیں</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- STEP 2: Pick a Time -->
        <div class="fl11-step-content fl11-step-2" id="fl11Step2" style="display: none;">
            <div class="fl11-content-header">
                <h1 class="fl11-bilingual">
                    <span>{{ __('Pick a Time:') }}</span>
                    <span class="fl11-ur" style="color: #64748b; font-size: 16px;">وقت کا انتخاب کریں</span>
                </h1>
            </div>

            <div class="fl11-content-body fl11-time-section">
                <div class="fl11-form-section fl11-full-width">
                    <div class="fl11-form-group">
                        <label class="fl11-bilingual">
                            <span>{{ __('Select Date') }} <span class="fl11-required">*</span></span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">تاریخ منتخب کریں</span>
                        </label>
                        <div class="fl11-select-wrapper">
                            <input type="text" name="appointment_date" id="datepicker" class="fl11-input" placeholder="{{ __('Select date') }}" readonly required>
                            <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                    </div>

                    <div class="fl11-time-slots-container">
                        <label class="fl11-bilingual">
                            <span>{{ __('Available Time Slots') }} <span class="fl11-required">*</span></span>
                            <span class="fl11-ur" style="font-size: 13px; opacity: 0.8; margin-top: 2px;">دستیاب اوقات</span>
                        </label>
                        <ul class="fl11-time-slots" id="timeSlotsContainer">
                            <p class="fl11-time-placeholder">{{ __('Please select a date to view available time slots') }}</p>
                        </ul>
                    </div>
                </div>

                <!-- Summary Panel for Step 2 -->
                <div class="fl11-summary-panel">
                    <div class="fl11-summary-header">
                        <h4 class="fl11-bilingual">
                            <span>{{ __('Summary') }}</span>
                            <span class="fl11-ur" style="font-size: 14px; color: #00e5cc;">خلاصہ</span>
                        </h4>
                        <svg class="fl11-summary-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="9"></line>
                            <line x1="9" y1="13" x2="15" y2="13"></line>
                            <line x1="9" y1="17" x2="12" y2="17"></line>
                        </svg>
                    </div>
                    <div class="fl11-summary-content">
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Service:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">سروس</span>
                            </div>
                            <span class="fl11-summary-value" id="summaryServiceStep2">{{ __('None yet') }}</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Date:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">تاریخ</span>
                            </div>
                            <span class="fl11-summary-value" id="summaryDate">{{ __('None yet') }}</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Time:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">وقت</span>
                            </div>
                            <span class="fl11-summary-value" id="summaryTime">{{ __('None yet') }}</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Cost:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">لاگت</span>
                            </div>
                            <span class="fl11-summary-value fl11-cost" id="summaryCostStep2">{{ $currency_symbol ?? '$' }}0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fl11-button-group">
                <button type="button" class="fl11-back-btn" id="fl11BackStep2">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>← {{ __('Back') }}</span>
                        <span class="fl11-ur" style="font-size: 11px;">واپس</span>
                    </div>
                </button>
                <button type="button" class="fl11-continue-btn" id="fl11ContinueStep2">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>{{ __('Continue') }} →</span>
                        <span class="fl11-ur" style="font-size: 13px; margin-top: 0;">جاری رکھیں</span>
                    </div>
                </button>
            </div>
        </div>

        @stack('shopping_cart')

        <!-- STEP 3: Additional Details -->
        <div class="fl11-step-content fl11-step-3" id="fl11Step3" style="display: none;">
            <div class="fl11-content-header">
                <h1 class="fl11-bilingual">
                    <span>{{ __('Additional Details:') }}</span>
                    <span class="fl11-ur" style="color: #64748b; font-size: 16px;">اضافی تفصیلات</span>
                </h1>
            </div>

            <div class="fl11-content-body">
                <div class="fl11-form-section fl11-full-width">
                    @if (!empty($files) && $files->value == 'on')
                        <div class="fl11-form-group">
                            <label>{{ $files->label ?? __('Attachment') }}</label>
                            <div class="fl11-file-upload">
                                <input type="file" name="attachment" id="attachment" class="fl11-file-input">
                                <label for="attachment" class="fl11-file-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    <span>{{ __('Choose file or drag here') }}</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    @if (!empty($custom_field) && $custom_field == 'on')
                        @foreach ($custom_fields as $custom_fld)
                            <div class="fl11-form-group">
                                <label>{{ $custom_fld->label }}
                                    @if ($custom_fld->required == 'on')
                                        <span class="fl11-required">*</span>
                                    @endif
                                </label>
                                {{-- Debug: Show field type --}}
                                {{-- Field Type: {{ $custom_fld->type }} --}}
                                @if ($custom_fld->type == 'text')
                                    <input type="text" name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                           class="fl11-input" placeholder="{{ $custom_fld->label }}"
                                           {{ $custom_fld->required == 'on' ? 'required' : '' }}>
                                @elseif($custom_fld->type == 'textarea')
                                    <textarea name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                              class="fl11-textarea" rows="4" placeholder="{{ $custom_fld->label }}"
                                              {{ $custom_fld->required == 'on' ? 'required' : '' }}></textarea>
                                @elseif($custom_fld->type == 'number')
                                    <input type="number" name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                           class="fl11-input" placeholder="{{ $custom_fld->label }}"
                                           {{ $custom_fld->required == 'on' ? 'required' : '' }}>
                                @elseif($custom_fld->type == 'date')
                                    <input type="date" name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                           class="fl11-input"
                                           {{ $custom_fld->required == 'on' ? 'required' : '' }}>
                                @elseif($custom_fld->type == 'checkbox')
                                    <div class="fl11-checkbox-group">
                                        @foreach ($options[$custom_fld->id] ?? [] as $option)
                                            <label class="fl11-checkbox-label">
                                                <input type="checkbox" name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}][]" 
                                                       value="{{ $option }}">
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($custom_fld->type == 'radio')
                                    <div class="fl11-radio-group">
                                        @foreach ($options[$custom_fld->id] ?? [] as $option)
                                            <label class="fl11-radio-label">
                                                <input type="radio" name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                                       value="{{ $option }}" {{ $custom_fld->required == 'on' ? 'required' : '' }}>
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($custom_fld->type == 'select')
                                    <div class="fl11-select-wrapper">
                                        <select name="values[{{ $custom_fld->type }}][{{ $custom_fld->label }}]" 
                                                class="fl11-select" {{ $custom_fld->required == 'on' ? 'required' : '' }}>
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($options[$custom_fld->id] ?? [] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <svg class="fl11-select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6,9 12,15 18,9"></polyline>
                                        </svg>
                                    </div>
                                @else
                                    {{-- Fallback for unknown type --}}
                                    <textarea name="values[textarea][{{ $custom_fld->label }}]" 
                                              class="fl11-textarea" rows="4" placeholder="{{ $custom_fld->label }}"
                                              {{ $custom_fld->required == 'on' ? 'required' : '' }}></textarea>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    @stack('view_additional_field')
                </div>
            </div>

            <div class="fl11-button-group">
                <button type="button" class="fl11-back-btn" id="fl11BackStep3">
                    ← {{ __('Back') }}
                </button>
                <button type="button" class="fl11-continue-btn" id="fl11ContinueStep3">
                    {{ __('Continue') }} →
                </button>
            </div>
        </div>

        <!-- STEP 4: Share Your Details -->
        <div class="fl11-step-content fl11-step-4" id="fl11Step4" style="display: none;">
            <div class="fl11-content-header">
                <h1 class="fl11-bilingual">
                    <span>{{ __('Share Your Details:') }}</span>
                    <span class="fl11-ur" style="color: #64748b; font-size: 16px;">اپنی تفصیلات بتائیں</span>
                </h1>
            </div>

            <div class="fl11-content-body">
                <div class="fl11-form-section fl11-full-width">
                    @php
                        // Ensure booking modes has at least one value as fallback
                        $bookingModes = !empty($bookingModes) && is_array($bookingModes) ? $bookingModes : ['1', '2'];
                        $hasRegistered = in_array('1', $bookingModes);
                        $hasGuest = in_array('2', $bookingModes);
                        $defaultTab = ($hasRegistered ? 'new-user' : 'guest-user');
                    @endphp
                    
                    <!-- User Type Tabs -->
                    <div class="fl11-user-tabs">
                        @if ($hasRegistered)
                            <button type="button" class="fl11-user-tab fl11-bilingual active" data-tab="new-user">
                                <span>{{ __('New User') }}</span>
                                <span class="fl11-ur" style="font-size: 11px;">نیا صارف</span>
                            </button>
                            <button type="button" class="fl11-user-tab fl11-bilingual" data-tab="existing-user">
                                <span>{{ __('Existing User') }}</span>
                                <span class="fl11-ur" style="font-size: 11px;">موجودہ صارف</span>
                            </button>
                        @endif
                        @if ($hasGuest)
                            <button type="button" class="fl11-user-tab fl11-bilingual {{ !$hasRegistered ? 'active' : '' }}" data-tab="guest-user">
                                <span>{{ __('Guest') }}</span>
                                <span class="fl11-ur" style="font-size: 11px;">مہمان</span>
                            </button>
                        @endif
                    </div>
                    <input type="hidden" name="type" id="selected_tab" value="{{ $defaultTab }}">

                    <!-- New User Form -->
                    @if ($hasRegistered)
                        <div class="fl11-user-form" id="new-user-form" style="display: block;">
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Full Name') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">پورا نام</span>
                                    </label>
                                    <input type="text" name="name" id="new_name" class="fl11-input" placeholder="{{ __('Enter your name') }}">
                                </div>
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Email') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">ای میل</span>
                                    </label>
                                    <input type="email" name="email" id="new_email" class="fl11-input" placeholder="{{ __('Enter your email') }}">
                                </div>
                            </div>
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Phone') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">فون</span>
                                    </label>
                                    <input type="tel" name="contact" id="new_contact" class="fl11-input" placeholder="{{ __('Enter phone number') }}">
                                </div>
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Password') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">پاس ورڈ</span>
                                    </label>
                                    <input type="password" name="password" id="new_password" class="fl11-input" placeholder="{{ __('Create password') }}">
                                </div>
                            </div>
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-full" style="width: 100%;">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Referred By') }}</span>
                                        <span class="fl11-ur" style="font-size: 13px;">کس نے بھیجا؟</span>
                                    </label>
                                    <input type="text" name="referred_by" class="fl11-input" placeholder="{{ __('Doctor Name (Optional)') }}">
                                </div>
                            </div>
                        </div>

                        <!-- Existing User Form -->
                        <div class="fl11-user-form" id="existing-user-form" style="display: none;">
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Email') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">ای میل</span>
                                    </label>
                                    <input type="email" name="email" id="existing_email" class="fl11-input" placeholder="{{ __('Enter your email') }}">
                                </div>
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Password') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">پاس ورڈ</span>
                                    </label>
                                    <input type="password" name="password" id="existing_password" class="fl11-input" placeholder="{{ __('Enter password') }}">
                                </div>
                            </div>
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-full" style="width: 100%;">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Referred By') }}</span>
                                        <span class="fl11-ur" style="font-size: 13px;">کس نے بھیجا؟</span>
                                    </label>
                                    <input type="text" name="referred_by" class="fl11-input" placeholder="{{ __('Doctor Name (Optional)') }}">
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Guest User Form -->
                    @if ($hasGuest)
                        <div class="fl11-user-form" id="guest-user-form" style="{{ !$hasRegistered ? 'display: block;' : 'display: none;' }}">
                            <div class="fl11-form-row">
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Full Name') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">پورا نام</span>
                                    </label>
                                    <input type="text" name="name" id="guest_name" class="fl11-input" placeholder="{{ __('Enter your name') }}">
                                </div>
                                <div class="fl11-form-group fl11-half">
                                    <label class="fl11-bilingual">
                                        <span>{{ __('Email') }} <span class="fl11-required">*</span></span>
                                        <span class="fl11-ur" style="font-size: 13px;">ای میل</span>
                                    </label>
                                    <input type="email" name="email" id="guest_email" class="fl11-input" placeholder="{{ __('Enter your email') }}">
                                </div>
                            </div>
                            <div class="fl11-form-group">
                                <label class="fl11-bilingual">
                                    <span>{{ __('Phone') }} <span class="fl11-required">*</span></span>
                                    <span class="fl11-ur" style="font-size: 13px;">فون</span>
                                </label>
                                <input type="tel" name="contact" id="guest_contact" class="fl11-input" placeholder="{{ __('Enter phone number') }}">
                            </div>
                            <div class="fl11-form-group">
                                <label class="fl11-bilingual">
                                    <span>{{ __('Referred By') }}</span>
                                    <span class="fl11-ur" style="font-size: 13px;">کس نے بھیجا؟</span>
                                </label>
                                <input type="text" name="referred_by" class="fl11-input" placeholder="{{ __('Doctor Name (Optional)') }}">
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="fl11-button-group">
                <button type="button" class="fl11-back-btn" id="fl11BackStep4">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>← {{ __('Back') }}</span>
                        <span class="fl11-ur" style="font-size: 11px;">واپس</span>
                    </div>
                </button>
                <button type="button" class="fl11-continue-btn" id="fl11ContinueStep4">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>{{ __('Continue to Payment') }} →</span>
                        <span class="fl11-ur" style="font-size: 13px; margin-top: 0;">ادائیگی کی تفصیلات</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- STEP 5: Payment -->
        <div class="fl11-step-content fl11-step-5" id="fl11Step5" style="display: none;">
            <div class="fl11-content-header">
                <h1 class="fl11-bilingual">
                    <span>{{ __('Payment:') }}</span>
                    <span class="fl11-ur" style="color: #64748b; font-size: 16px;">ادائیگی</span>
                </h1>
            </div>

            <div class="fl11-content-body">
                <div class="fl11-form-section fl11-full-width">
                    <div class="fl11-payment-summary">
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Service:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">سروس</span>
                            </div>
                            <span class="fl11-summary-value" id="paymentService">-</span>
                        </div>
                        <div class="fl11-summary-row">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Date & Time:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">تاریخ اور وقت</span>
                            </div>
                            <span class="fl11-summary-value" id="paymentDateTime">-</span>
                        </div>
                        <div class="fl11-summary-row fl11-total">
                            <div class="fl11-bilingual">
                                <span class="fl11-summary-label">{{ __('Total:') }}</span>
                                <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">کل لاگت</span>
                            </div>
                            <span class="fl11-summary-value fl11-cost" id="paymentTotal">{{ $currency_symbol ?? '$' }}0</span>
                        </div>
                        @stack('apply_coupon')
                        @stack('deposit_payment')
                    </div>

                    <div class="fl11-payment-methods">
                        <label class="fl11-payment-option">
                            <input type="radio" name="payment_method" value="manually" checked>
                            <span class="fl11-payment-card">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                    <line x1="1" y1="10" x2="23" y2="10"></line>
                                </svg>
                                <span class="fl11-bilingual">
                                    <span>{{ __('Pay at Location') }}</span>
                                    <span class="fl11-ur" style="font-size: 12px; margin-top: -2px;">کاؤنٹر پر ادائیگی کریں</span>
                                </span>
                            </span>
                        </label>
                        @stack('appointment_payment')
                    </div>
                </div>
            </div>

            <div class="fl11-button-group">
                <button type="button" class="fl11-back-btn" id="fl11BackStep5">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>← {{ __('Back') }}</span>
                        <span class="fl11-ur" style="font-size: 11px;">واپس</span>
                    </div>
                </button>
                <button type="submit" class="fl11-continue-btn fl11-submit-btn" id="fl11SubmitBtn">
                    <div class="fl11-bilingual" style="align-items: center;">
                        <span>{{ __('Confirm Booking') }} ✓</span>
                        <span class="fl11-ur" style="font-size: 13px; margin-top: 0;">بکنگ کی تصدیق کریں</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- STEP 6: Done (Success) -->
        <div class="fl11-step-content fl11-step-6" id="fl11Step6" style="display: none;">
            <div class="fl11-success-content">
                <div class="fl11-success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                
                <h1 class="fl11-bilingual" style="justify-content: center;">
                    <span>{{ __('Booking Confirmed!') }}</span>
                    <span class="fl11-ur" style="font-size: 20px; color: #00e5cc;">بکنگ کی تصدیق ہو گئی!</span>
                </h1>
                <p class="fl11-bilingual" style="justify-content: center; opacity: 0.8;">
                    <span>{{ __('Your appointment has been successfully booked.') }}</span>
                    <span class="fl11-ur" style="font-size: 14px;">آپ کی ملاقات کامیابی کے ساتھ بک کر لی گئی ہے۔</span>
                </p>
                
                <div class="fl11-booking-details">
                    <div class="fl11-detail-row">
                        <div class="fl11-bilingual">
                            <span class="fl11-detail-label">{{ __('Booking Number:') }}</span>
                            <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">بکنگ نمبر</span>
                        </div>
                        <span class="fl11-detail-value" id="bookingNumber">-</span>
                    </div>
                    <div class="fl11-detail-row">
                        <div class="fl11-bilingual">
                            <span class="fl11-detail-label">{{ __('Service:') }}</span>
                            <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">سروس</span>
                        </div>
                        <span class="fl11-detail-value" id="bookingService">-</span>
                    </div>
                    <div class="fl11-detail-row">
                        <div class="fl11-bilingual">
                            <span class="fl11-detail-label">{{ __('Date & Time:') }}</span>
                            <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">تاریخ اور وقت</span>
                        </div>
                        <span class="fl11-detail-value" id="bookingDateTime">-</span>
                    </div>
                    <div class="fl11-detail-row">
                        <div class="fl11-bilingual">
                            <span class="fl11-detail-label">{{ __('Location:') }}</span>
                            <span class="fl11-ur" style="font-size: 11px; opacity: 0.7;">جگہ</span>
                        </div>
                        <span class="fl11-detail-value" id="bookingLocation">-</span>
                    </div>
                </div>
                
                <div class="fl11-qr-section">
                    <h4 class="fl11-bilingual" style="justify-content: center;">
                        <span>{{ __('Scan to View Details') }}</span>
                        <span class="fl11-ur" style="font-size: 13px;">تفصیلات دیکھنے کے لیے اسکین کریں</span>
                    </h4>
                    <div id="appointmentQrCode" class="fl11-qr-code"></div>
                </div>
                
                <a href="{{ route('appointments.form') }}" class="fl11-continue-btn">
                    <div class="fl11-bilingual" style="align-items: center; justify-content: center;">
                        <span>{{ __('Book Another Appointment') }}</span>
                        <span class="fl11-ur" style="font-size: 13px; margin-top: 0;">ایک اور بکنگ کریں</span>
                    </div>
                </a>
            </div>
        </div>

        {{ Form::close() }}
    </div>
</div>

@stack('abuse_btn')
@stack('book_appointment_form_layout1')
@stack('collaborative_services')
@endsection

@push('script')
<script src="{{ asset('packages/workdo/LandingPage/src/Resources/assets/js/jquery.qrcode.js') }}"></script>
<script>
    // Formlayout11 specific JavaScript
    (function() {
        'use strict';
        
        // Update sidebar step indicators
        window.fl11UpdateStep = function(stepNumber) {
            document.querySelectorAll('.fl11-step').forEach(function(step, index) {
                step.classList.remove('active', 'completed');
                if (index + 1 < stepNumber) {
                    step.classList.add('completed');
                } else if (index + 1 === stepNumber) {
                    step.classList.add('active');
                }
            });
            
            // Update sidebar header text (Bilingual)
            var stepLabels = [
                'Service Selection <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">سروس کا انتخاب</small>', 
                'Pick a Time <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">وقت کا انتخاب</small>', 
                'Additional Details <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">اضافی تفصیلات</small>', 
                'Share Your Details <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">ذاتی تفصیلات</small>', 
                'Payment <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">ادائیگی</small>', 
                'Done <br><small class="fl11-ur" style="color: #64748b; font-size:12px;">مکمل</small>'
            ];
            var headerH3 = document.querySelector('.fl11-sidebar-header h3');
            if (headerH3 && stepLabels[stepNumber - 1]) {
                headerH3.innerHTML = stepLabels[stepNumber - 1];
            }
        };
        
        // Show specific step content
        window.fl11ShowStep = function(stepNumber) {
            document.querySelectorAll('.fl11-step-content').forEach(function(content) {
                content.style.display = 'none';
            });
            var stepContent = document.getElementById('fl11Step' + stepNumber);
            if (stepContent) {
                stepContent.style.display = 'block';
            }
            fl11UpdateStep(stepNumber);
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            // Category card single-select
            var categoryCards = document.querySelectorAll('.fl11-service-card');
            var categorySelect = document.getElementById('categorySelect');
            
            categoryCards.forEach(function(card) {
                card.addEventListener('click', function() {
                    // Deselect all cards
                    categoryCards.forEach(function(c) {
                        c.classList.remove('selected');
                        c.querySelector('.fl11-card-checkbox').classList.remove('checked');
                    });
                    
                    // Select clicked card
                    this.classList.add('selected');
                    this.querySelector('.fl11-card-checkbox').classList.add('checked');
                    
                    // Update hidden select
                    var categoryId = this.dataset.categoryId;
                    var categoryName = this.dataset.categoryName;
                    categorySelect.value = categoryId;
                    
                    // Update summary
                    document.getElementById('summaryCategory').textContent = categoryName;
                    
                    // Trigger change event for AJAX service loading
                    var event = new Event('change', { bubbles: true });
                    categorySelect.dispatchEvent(event);
                });
            });
            
            // Sync dropdown with cards
            categorySelect.addEventListener('change', function() {
                var selectedId = this.value;
                categoryCards.forEach(function(card) {
                    if (card.dataset.categoryId === selectedId) {
                        card.classList.add('selected');
                        card.querySelector('.fl11-card-checkbox').classList.add('checked');
                        document.getElementById('summaryCategory').textContent = card.dataset.categoryName;
                    } else {
                        card.classList.remove('selected');
                        card.querySelector('.fl11-card-checkbox').classList.remove('checked');
                    }
                });
            });
            
            // Service select change - update summary
            var serviceSelect = document.getElementById('serviceSelect');
            if (serviceSelect && typeof jQuery !== 'undefined' && jQuery.fn.niceSelect) {
                // Initialize niceSelect if available
                try {
                    $(serviceSelect).niceSelect();
                } catch(e) {
                    console.warn('FL11: niceSelect initialization failed', e);
                }
            }
            
            // Handle both native and jQuery change events
            if (serviceSelect) {
                // Native change event (for programmatic updates and direct user selection)
                serviceSelect.addEventListener('change', function() {
                    updateServicePrice();
                });
                
                // jQuery change event (for niceSelect compatibility)
                if (typeof jQuery !== 'undefined') {
                    $(serviceSelect).on('change', function() {
                        updateServicePrice();
                    });
                }
            }
            
            // Function to update price display
            function updateServicePrice() {
                var serviceSelect = document.getElementById('serviceSelect');
                var selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                var selectedServiceId = serviceSelect.value;
                
                console.log('FL11: updateServicePrice called', {
                    selectedIndex: serviceSelect.selectedIndex,
                    selectedValue: selectedOption ? selectedOption.value : null,
                    selectedText: selectedOption ? selectedOption.text : null,
                    dataset: selectedOption ? selectedOption.dataset : null,
                    dataPrice: selectedOption ? selectedOption.dataset.price : null,
                    servicePricesFromWindow: window.servicePrices ? window.servicePrices[selectedServiceId] : null,
                    allOptions: Array.from(serviceSelect.options).map(opt => ({
                        value: opt.value,
                        text: opt.text,
                        dataPrice: opt.dataset.price
                    }))
                });
                
                if (selectedOption && selectedOption.value) {
                    document.getElementById('summaryService').textContent = selectedOption.text;
                    document.getElementById('summaryServiceStep2').textContent = selectedOption.text;
                    
                    // Get price - first try data attribute, then try global servicePrices object
                    var price = selectedOption.dataset.price;
                    if (!price && window.servicePrices && window.servicePrices[selectedServiceId]) {
                        price = window.servicePrices[selectedServiceId];
                    }
                    price = price || '0';
                    
                    console.log('FL11: Service selected, final price:', price); // Debug log
                    var currencySymbol = '{{ $currency_symbol ?? "$" }}';
                    var priceDisplay = currencySymbol + ' ' + price;
                    console.log('FL11: Setting price display to:', priceDisplay);
                    document.getElementById('summaryCost').textContent = priceDisplay;
                    document.getElementById('summaryCostStep2').textContent = priceDisplay;
                    document.getElementById('summaryCostNote').textContent = selectedOption.text;
                } else {
                    document.getElementById('summaryService').textContent = '{{ __("None yet") }}';
                    console.log('FL11: No service selected');
                }
            }
            
            // User type tabs
            var userTabs = document.querySelectorAll('.fl11-user-tab');
            var selectedTabInput = document.getElementById('selected_tab');
            
            // Debug: Check what forms exist
            console.log('FL11 Debug: Booking modes =', @json($bookingModes));
            console.log('FL11 Debug: Available forms =', Array.from(document.querySelectorAll('.fl11-user-form')).map(f => f.id));
            
            // Initialize - show the default active form on page load
            if (selectedTabInput) {
                var defaultTab = selectedTabInput.value;
                console.log('FL11 Step 4 Init: Default tab =', defaultTab);
                
                // Check if any forms exist
                var allForms = document.querySelectorAll('.fl11-user-form');
                if (allForms.length === 0) {
                    console.error('FL11 Step 4 Init: No user forms found! Check Blade conditions.');
                    return;
                }
                
                // Hide all forms first
                allForms.forEach(function(form) {
                    form.style.display = 'none';
                });
                
                // Show the default form
                var defaultForm = document.getElementById(defaultTab + '-form');
                if (defaultForm) {
                    defaultForm.style.display = 'block';
                    console.log('FL11 Step 4 Init: Showing form', defaultTab + '-form');
                } else {
                    console.error('FL11 Step 4 Init: Form not found', defaultTab + '-form');
                    console.error('FL11 Step 4 Init: Expected form ID:', defaultTab + '-form');
                    console.error('FL11 Step 4 Init: Available forms:', Array.from(allForms).map(f => f.id));
                }
            }
            
            userTabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    userTabs.forEach(function(t) { t.classList.remove('active'); });
                    this.classList.add('active');
                    
                    var tabType = this.dataset.tab;
                    selectedTabInput.value = tabType;
                    
                    // Show/hide forms
                    document.querySelectorAll('.fl11-user-form').forEach(function(form) {
                        form.style.display = 'none';
                    });
                    var targetForm = document.getElementById(tabType + '-form');
                    if (targetForm) {
                        targetForm.style.display = 'block';
                    }
                });
            });
            
            // Step navigation - Continue buttons
            document.getElementById('fl11ContinueStep1').addEventListener('click', function() {
                if (validateStep1()) {
                    fl11ShowStep(2);
                }
            });
            
            document.getElementById('fl11ContinueStep2').addEventListener('click', function() {
                if (validateStep2()) {
                    // Check if step 3 has content
                    var hasAdditionalFields = {{ (!empty($files) && $files->value == 'on') || (!empty($custom_field) && $custom_field == 'on') ? 'true' : 'false' }};
                    if (hasAdditionalFields) {
                        fl11ShowStep(3);
                    } else {
                        fl11ShowStep(4);
                    }
                }
            });
            
            document.getElementById('fl11ContinueStep3').addEventListener('click', function() {
                fl11ShowStep(4);
            });
            
            document.getElementById('fl11ContinueStep4').addEventListener('click', function() {
                if (validateStep4()) {
                    updatePaymentSummary();
                    fl11ShowStep(5);
                }
            });
            
            // Back buttons
            document.getElementById('fl11BackStep2').addEventListener('click', function() {
                fl11ShowStep(1);
            });
            
            document.getElementById('fl11BackStep3').addEventListener('click', function() {
                fl11ShowStep(2);
            });
            
            document.getElementById('fl11BackStep4').addEventListener('click', function() {
                var hasAdditionalFields = {{ (!empty($files) && $files->value == 'on') || (!empty($custom_field) && $custom_field == 'on') ? 'true' : 'false' }};
                if (hasAdditionalFields) {
                    fl11ShowStep(3);
                } else {
                    fl11ShowStep(2);
                }
            });
            
            document.getElementById('fl11BackStep5').addEventListener('click', function() {
                fl11ShowStep(4);
            });
            
            // CRITICAL: Form submission handler for Step 5 -> Step 6 (Confirmation)
            var appointmentForm = document.getElementById('appointment-form');
            if (appointmentForm) {
                // Mark that FL11 is handling submission
                appointmentForm.dataset.fl11Handler = 'true';
                
                appointmentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('FL11: Form submission initiated');
                    
                    // Prevent double submissions
                    var submitBtn = document.getElementById('fl11SubmitBtn');
                    if (submitBtn.disabled) {
                        console.warn('FL11: Form submission already in progress');
                        return;
                    }
                    
                    // Show loading state
                    var originalText = submitBtn.textContent;
                    submitBtn.disabled = true;
                    submitBtn.textContent = '{{ __("Processing...") }} ⏳';
                    
                    // Validate Step 5 before submission
                    if (!validateStep5()) {
                        console.error('FL11: Step 5 validation failed');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        showToast('{{ __("Please fill in all required fields") }}', 'error');
                        return;
                    }
                    
                    // Disable hidden form fields to prevent empty values from being submitted
                    var hiddenForms = appointmentForm.querySelectorAll('.fl11-user-form');
                    hiddenForms.forEach(function(form) {
                        if (form.style.display === 'none') {
                            var inputs = form.querySelectorAll('input, select, textarea');
                            inputs.forEach(function(input) {
                                input.disabled = true;
                            });
                        }
                    });
                    
                    // Collect form data
                    var formData = new FormData(appointmentForm);
                    
                    // Log form data for debugging
                    console.log('FL11: Form data collected:');
                    for (var pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }
                    console.log('FL11: Submitting to {{ route("appointment.form.submit") }}');
                    
                    // Re-enable all fields after collecting data
                    hiddenForms.forEach(function(form) {
                        var inputs = form.querySelectorAll('input, select, textarea');
                        inputs.forEach(function(input) {
                            input.disabled = false;
                        });
                    });
                    
                    // Get CSRF token
                    var csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken || !csrfToken.content) {
                        console.error('FL11: CSRF token not found!');
                        showToast('{{ __("Security token missing. Please refresh the page.") }}', 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        return;
                    }
                    
                    // Submit form via AJAX
                    fetch('{{ route("appointment.form.submit") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken.content,
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        console.log('FL11: Response received, status:', response.status);
                        if (!response.ok) {
                            throw new Error('HTTP error ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('FL11: Response data:', data);
                        
                        if (data.status === 'success') {
                            console.log('FL11: Appointment created successfully');
                            console.log('FL11: Response data:', data);
                            
                            // Get appointment ID from response (preferred) or extract from URL
                            var appointmentId = data.appointment_id || null;
                            if (!appointmentId && data.url && data.url.includes('appointment=')) {
                                appointmentId = data.url.split('appointment=')[1];
                            }
                            
                            console.log('FL11: Appointment ID:', appointmentId);
                            
                            // Prepare appointment confirmation data
                            var appointmentData = {
                                appointment_id: appointmentId,
                                booking_number: data.appointment_number || ('{{ __("Booking #") }}' + appointmentId),
                                service: document.querySelector('[name="service"] option:checked')?.textContent || '-',
                                date: document.querySelector('[name="appointment_date"]')?.value || '-',
                                time: document.querySelector('[name="duration"]')?.value || '-',
                                location: document.querySelector('[name="location"] option:checked')?.textContent || '-',
                                staff: document.querySelector('[name="staff"] option:checked')?.textContent || '-'
                            };
                            
                            console.log('FL11: Appointment data prepared:', appointmentData);
                            
                            // Display confirmation with QR code
                            window.displayBookingConfirmation(appointmentData);
                            
                            // Show success message
                            showToast(data.message || '{{ __("Booking confirmed successfully!") }}', 'success');
                        } else {
                            console.error('FL11: Submission failed with status:', data.status);
                            showToast(data.message || data.error || '{{ __("Booking failed. Please try again.") }}', 'error');
                            
                            // Re-enable submit button on error
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('FL11: Form submission error:', error);
                        console.error('FL11: Error type:', error.name);
                        console.error('FL11: Error message:', error.message);
                        
                        var errorMessage = '{{ __("An error occurred during booking. Please try again.") }}';
                        if (error.message.includes('Failed to fetch')) {
                            errorMessage = '{{ __("Network error. Please check your connection and try again.") }}';
                        } else if (error.message.includes('HTTP error')) {
                            errorMessage = '{{ __("Server error. Please contact support.") }}';
                        }
                        
                        showToast(errorMessage, 'error');
                        
                        // Re-enable submit button on error
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    });
                });
            } else {
                console.error('FL11: Form element with id="appointment-form" not found!');
            }
            
            // Datepicker date change - update summary and load time slots
            // Use event delegation to handle dynamically reinitialized datepicker
            $(document).on('changeDate', '#datepicker', function(e) {
                var selectedDate = $(this).val();
                $('#summaryDate').text(selectedDate);
                
                // Trigger time slot loading with required parameters
                var selectedService = $('#serviceSelect').val();
                var selectedStaff = $('#staffSelect').val();
                
                console.log('Datepicker changed:', {
                    date: selectedDate,
                    service: selectedService,
                    staff: selectedStaff
                });
                
                if (typeof appointmentTimeSlot === 'function' && selectedService && selectedDate) {
                    appointmentTimeSlot(selectedService, selectedDate, selectedStaff);
                } else if (!selectedService) {
                    showToast('{{ __("Please select a service first") }}', 'error');
                }
            });
            
            // Time slot selection - update summary with 12-hour format
            $(document).on('change', 'input[name="duration"]', function() {
                var timeSelected = document.querySelector('input[name="duration"]:checked');
                if (timeSelected) {
                    // Convert time to 12-hour format for display
                    function convertTo12Hour(time24) {
                        if (!time24) return time24;
                        var parts = time24.split(':');
                        var hour = parseInt(parts[0]);
                        var minute = parts[1] || '00';
                        var period = hour >= 12 ? 'PM' : 'AM';
                        hour = hour % 12;
                        if (hour === 0) hour = 12;
                        return (hour < 10 ? '0' : '') + hour + ':' + minute + ' ' + period;
                    }
                    
                    var timeValue = timeSelected.value; // Format: "HH:MM-HH:MM"
                    var timeParts = timeValue.split('-');
                    var startTime = convertTo12Hour(timeParts[0].trim());
                    var endTime = convertTo12Hour(timeParts[1].trim());
                    document.getElementById('summaryTime').textContent = startTime + ' - ' + endTime;
                }
            });
            
            // Validation functions
            function validateStep1() {
                var category = document.getElementById('categorySelect').value;
                var service = document.getElementById('serviceSelect').value;
                
                if (!category) {
                    showToast('{{ __("Please select a category") }}', 'error');
                    return false;
                }
                if (!service) {
                    showToast('{{ __("Please select a service") }}', 'error');
                    return false;
                }
                return true;
            }
            
            function validateStep2() {
                var date = document.getElementById('datepicker').value;
                var timeSelected = document.querySelector('input[name="duration"]:checked');
                
                if (!date) {
                    showToast('{{ __("Please select a date") }}', 'error');
                    return false;
                }
                if (!timeSelected) {
                    showToast('{{ __("Please select a time slot") }}', 'error');
                    return false;
                }
                
                // Convert time to 12-hour format for display
                function convertTo12Hour(time24) {
                    if (!time24) return time24;
                    var parts = time24.split(':');
                    var hour = parseInt(parts[0]);
                    var minute = parts[1] || '00';
                    var period = hour >= 12 ? 'PM' : 'AM';
                    hour = hour % 12;
                    if (hour === 0) hour = 12;
                    return (hour < 10 ? '0' : '') + hour + ':' + minute + ' ' + period;
                }
                
                var timeValue = timeSelected.value; // Format: "HH:MM-HH:MM"
                var timeParts = timeValue.split('-');
                var startTime = convertTo12Hour(timeParts[0].trim());
                var endTime = convertTo12Hour(timeParts[1].trim());
                document.getElementById('summaryTime').textContent = startTime + ' - ' + endTime;
                return true;
            }
            
            function validateStep4() {
                var userType = document.getElementById('selected_tab').value;
                var isValid = true;
                
                if (userType === 'guest-user') {
                    var name = document.getElementById('guest_name').value;
                    var email = document.getElementById('guest_email').value;
                    var contact = document.getElementById('guest_contact').value;
                    
                    if (!name || !email || !contact) {
                        showToast('{{ __("Please fill in all required fields") }}', 'error');
                        isValid = false;
                    }
                } else if (userType === 'new-user') {
                    var name = document.getElementById('new_name').value;
                    var email = document.getElementById('new_email').value;
                    var contact = document.getElementById('new_contact').value;
                    var password = document.getElementById('new_password').value;
                    
                    if (!name || !email || !contact || !password) {
                        showToast('{{ __("Please fill in all required fields") }}', 'error');
                        isValid = false;
                    }
                } else if (userType === 'existing-user') {
                    var email = document.getElementById('existing_email').value;
                    var password = document.getElementById('existing_password').value;
                    
                    if (!email || !password) {
                        showToast('{{ __("Please fill in all required fields") }}', 'error');
                        isValid = false;
                    }
                }
                
                return isValid;
            }
            
            function validateStep5() {
                // Step 5 (Payment) validation
                // Check if payment method is selected (if applicable)
                var paymentMethod = document.querySelector('input[name="payment"]:checked');
                
                // For this form, payment is manually set via hidden input, so we just return true
                // If you need to validate payment details, add checks here
                console.log('FL11: Step 5 validation - payment method validated');
                return true;
            }
            
            function updatePaymentSummary() {
                var service = document.getElementById('summaryService').textContent;
                var date = document.getElementById('summaryDate').textContent;
                var time = document.getElementById('summaryTime').textContent;
                var cost = document.getElementById('summaryCost').textContent;
                
                document.getElementById('paymentService').textContent = service;
                document.getElementById('paymentDateTime').textContent = date + ' at ' + time;
                document.getElementById('paymentTotal').textContent = cost;
            }
            
            function showToast(message, type) {
                if (typeof toastr !== 'undefined') {
                    toastr[type](message);
                } else {
                    alert(message);
                }
            }
            
            // Function to display booking confirmation with QR code
            window.displayBookingConfirmation = function(appointmentData) {
                try {
                    console.log('FL11: displayBookingConfirmation called with data:', appointmentData);
                    
                    // Populate booking details
                    if (appointmentData.booking_number) {
                        document.getElementById('bookingNumber').textContent = appointmentData.booking_number;
                    }
                    if (appointmentData.service) {
                        document.getElementById('bookingService').textContent = appointmentData.service;
                    }
                    if (appointmentData.date && appointmentData.time) {
                        document.getElementById('bookingDateTime').textContent = appointmentData.date + ' at ' + appointmentData.time;
                    }
                    if (appointmentData.location) {
                        document.getElementById('bookingLocation').textContent = appointmentData.location;
                    }
                    if (appointmentData.staff) {
                        document.getElementById('bookingStaff').textContent = appointmentData.staff;
                    }
                    
                    // Hide any loading animations
                    var loader = document.getElementById('loader');
                    if (loader) {
                        loader.style.display = 'none';
                    }
                    
                    // Generate QR code if appointment ID is available
                    if (appointmentData.appointment_id) {
                        var appointmentUrl = window.location.origin + '/find-appointment?id=' + appointmentData.appointment_id;
                        console.log('FL11: Generating QR code for URL:', appointmentUrl);
                        generateQRCode(appointmentUrl, 'appointmentQrCode');
                    } else {
                        console.warn('FL11: No appointment ID available for QR code');
                    }
                    
                    // Show confirmation step
                    fl11ShowStep(6);
                    console.log('FL11: Confirmation step displayed');
                } catch (error) {
                    console.error('FL11 Error displaying booking confirmation:', error);
                    showToast('{{ __("Error displaying confirmation") }}', 'error');
                }
            };
            
            // Function to generate QR code
            window.generateQRCode = function(text, elementId) {
                try {
                    console.log('FL11: generateQRCode called with text:', text, 'elementId:', elementId);
                    var element = document.getElementById(elementId);
                    if (!element) {
                        console.error('FL11: QR Code element not found:', elementId);
                        return;
                    }
                    
                    // Clear existing QR code
                    element.innerHTML = '';
                    
                    // Use fallback API directly for faster, more reliable generation
                    // QR Code will be generated immediately via external API
                    console.log('FL11: Using QR code API fallback for immediate generation');
                    var fallbackImg = document.createElement('img');
                    var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(text);
                    fallbackImg.src = qrUrl;
                    fallbackImg.alt = '{{ __("Appointment QR Code") }}';
                    fallbackImg.style.maxWidth = '150px';
                    fallbackImg.style.display = 'block';
                    fallbackImg.style.margin = '0 auto';
                    fallbackImg.onload = function() {
                        console.log('FL11: QR Code image loaded successfully');
                    };
                    fallbackImg.onerror = function() {
                        console.error('FL11: QR Code image failed to load');
                        // Show error message
                        element.innerHTML = '<div style="color: red; text-align: center;">{{ __("Unable to load QR code") }}</div>';
                    };
                    element.appendChild(fallbackImg);
                    
                } catch (error) {
                    console.error('FL11 Error generating QR code:', error);
                    var element = document.getElementById(elementId);
                    if (element) {
                        element.innerHTML = '<div style="color: red;">{{ __("Error generating QR code") }}</div>';
                    }
                }
            };
        });
    })();
</script>
@endpush

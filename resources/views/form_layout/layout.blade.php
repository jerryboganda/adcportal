<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="form-one">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $business->name }}</title>
    <meta name="description" content="form-one">
    <meta name="keywords" content="form-one">
    <link rel="icon" href="{{ asset('form_layouts/' . $business->layouts . '/images/favicon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('form_layouts/' . $business->layouts . '/css/main-style.css') }}">
    <link rel="stylesheet" href="{{ asset('form_layouts/' . $business->layouts . '/css/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('module_assets/loader.css') }}">
    <link rel="stylesheet" href="{{ asset('form_layouts/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap-datepicker.min.css') }}">

    {{-- pwa customer app --}}
    <meta name="mobile-wep-app-capable" content="yes">
    <meta name="apple-mobile-wep-app-capable" content="yes">
    <meta name="msapplication-starturl" content="/">
    @if ($business->enable_pwa == 'on')
        <link rel="manifest" href="{{ get_file('uploads/theme_app/business_' . $business->id . '/manifest.json') }}" />
    @endif
    @if (!empty($business->pwa_business($business->slug)->theme_color))
        <meta name="theme-color" content="{{ $business->pwa_business($business->slug)->theme_color }}" />
    @endif
    @if (!empty($business->pwa_business($business->slug)->background_color))
        <meta name="apple-mobile-web-app-status-bar"
            content="{{ $business->pwa_business($business->slug)->background_color }}" />
    @endif
    @if (module_is_active('AdditionalCustomField'))
        <link rel="stylesheet"
            href="{{ asset('packages/workdo/AdditionalCustomField/src/Resources/assets/custom.css') }}">
    @endif
    {{-- end pwa customer app --}}

    {{-- custom-css --}}
    @if (!empty($customCss))
        <style type="text/css">
            {{ htmlspecialchars_decode($customCss) }}
        </style>
    @endif
    @stack('css')
    <style>
        #paiementpro-info .radio-group label::before,
        #paiementpro-info .radio-group label::after {
            display: none;
        }
    </style>
</head>

@php
$currency_setting = json_encode(
        Arr::only(getCompanyAllSetting($business->created_by), [
            'site_currency_symbol_position',
            'currency_format',
            'currency_space',
            'site_currency_symbol_name',
            'defult_currancy_symbol',
            'defult_currancy',
            'float_number',
            'decimal_separator',
            'thousand_separator',
        ]),
    );
@endphp

<body class="form-one {{ \App\Models\Business::forms()[$business->layouts][$business->theme_color]['theme_name'] }}">
    @if (isset($pixelScript))
        @foreach ($pixelScript as $script)
            <?= $script ?>
        @endforeach
    @endif

    @include('web_layouts.appointment-tracking')
    @yield('form_content')
    <div class="top-0 p-3 position-fixed end-0" style="z-index: 99999">
        <div id="liveToast" class="text-white toast fade" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"> </div>
                <button type="button" class="m-auto btn-close btn-close-white me-2" data-bs-dismiss="toast"
                    aria-label="Close"></button>
            </div>
        </div>
    </div>

    <div id="loader" class="loader-wrappers" style="display: none;">
        <span class="site-loaders"> </span>
        <h4 class="loader-content"> {{ __('Loading . . .') }} </h4>
    </div>

    <script src="{{ asset('form_layouts/' . $business->layouts . '/js/jquery.min.js') }}"></script>
    <script src="{{ asset('form_layouts/' . $business->layouts . '/js/custom.js') }}"></script>
    <script src="{{ asset('assets/js/custom-bootstrap.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('module_assets/loader.js') }}"></script>
    <script src="{{ asset('js/custom.js') }}"></script>

    @if ($business->enable_pwa == 'on')
        <script type="text/javascript">
            const container = document.querySelector("body")

            const coffees = [];

            if ("serviceWorker" in navigator) {
                window.addEventListener("load", function() {
                    navigator.serviceWorker
                        .register("{{ asset('packages/workdo/PWA/src/Resources/assets/js/serviceWorker.js') }}")
                        .then(res => console.log("service worker registered"))
                        .catch(err => console.log("service worker not registered", err))

                })
            }
        </script>
    @endif
    <script>
        $(document).ready(function() {

            // Category wise service dropdown start
            $('#categorySelect').change(function() {
                var categoryId = $(this).val();
                var businessSlug = '{{ $business->slug }}';

                if (categoryId) {
                    fetchServicesByCategory(categoryId, businessSlug);
                } else {
                    // Clear and disable service dropdown
                    $('#serviceSelect').empty().prop('disabled', true).append('<option value="" disabled selected>{{ __("Select category first") }}</option>');
                    $('#serviceSelect').niceSelect('update');
                    // Clear staff dropdown
                    $('#staffSelect').empty().append('<option value="" disabled selected>{{ __("Select staff") }}</option>');
                    $('#staffSelect').niceSelect('update');
                }
            });

            function fetchServicesByCategory(categoryId, businessSlug) {
                $('#loader').fadeIn();
                $.ajax({
                    url: '{{ route('get.services.by.category') }}',
                    method: 'GET',
                    data: {
                        category_id: categoryId,
                        business_slug: businessSlug
                    },
                    success: function(response) {
                        $('#loader').fadeOut();
                        console.log('Layout: Services AJAX response:', response);
                        if (response.success && response.services) {
                            console.log('Layout: Services array length:', response.services.length);
                            console.log('Layout: First service:', response.services[0]);
                            updateServiceDropdown(response.services);
                        } else {
                            alert('Error: ' + (response.error || 'Unable to load services'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#loader').fadeOut();
                        console.error('Error fetching services:', error);
                        console.error('XHR response:', xhr.responseText);
                        alert('Failed to load services. Please try again.');
                    }
                });
            }

            // Store service prices in a global object to avoid data attribute issues with niceSelect
            window.servicePrices = {};

            function updateServiceDropdown(services) {
                var serviceSelect = $('#serviceSelect');
                serviceSelect.empty().prop('disabled', false);
                
                serviceSelect.append('<option value="" disabled selected>{{ __("Select services") }}</option>');
                
                // Clear previous prices and populate with new services
                window.servicePrices = {};
                
                $.each(services, function(index, service) {
                    var option = $('<option></option>')
                        .attr('value', service.id)
                        .attr('data-price', service.price)
                        .attr('class', 'service')
                        .text(service.name);
                    serviceSelect.append(option);
                    
                    // Store price in global object as backup
                    window.servicePrices[service.id] = service.price;
                    
                    // Debug: Log first service to verify price attribute
                    if (index === 0) {
                        console.log('Layout: First service appended -', {
                            id: service.id,
                            name: service.name,
                            price: service.price,
                            dataset_price: option.attr('data-price'),
                            in_servicePrices: window.servicePrices[service.id]
                        });
                    }
                });
                
                console.log('Layout: Total services added:', services.length);
                console.log('Layout: servicePrices object:', window.servicePrices);
                
                serviceSelect.niceSelect('update');
                
                // Clear staff dropdown when services change (but don't disable - let the existing logic handle it)
                $('#staffSelect').empty().append('<option value="" disabled selected>{{ __("Select staff") }}</option>');
                $('#staffSelect').niceSelect('update');
                
                // Use timeout to ensure niceSelect is fully rendered before triggering change
                setTimeout(function() {
                    console.log('Layout: Triggering service select change event');
                    serviceSelect.trigger('change');
                }, 100);
            }

            // Category wise service dropdown end

            //service & location wise staff dropdown start
            $('#serviceSelect, #locationSelect').change(function() {
                var serviceValue = $('#serviceSelect').val();
                var locationValue = $('#locationSelect').val();

                // Check if both service and location are selected
                if (serviceValue && locationValue) {
                    fetchStaffData(serviceValue, locationValue);
                } else {
                    // Clear staff dropdown if either service or location is not selected
                    $('#staffSelect').empty();
                }
            });

            function fetchStaffData(serviceId, locationId) {
                $('#loader').fadeIn();
                $.ajax({
                    url: '{{ route('get.staff.data') }}',
                    method: 'GET',
                    data: {
                        service: serviceId,
                        location: locationId
                    },
                    success: function(response) {
                        $('#loader').fadeOut();
                        updateStaffDropdown(response);
                    },
                    error: function(xhr, status, error) {
                        $('#loader').fadeOut();
                        console.error('Error fetching staff data:', error);
                    }
                });
            }

            function updateStaffDropdown(staffData) {
                var staffSelect = $('#staffSelect');

                staffSelect.empty(); // Clear existing options
                staffSelect.append($('<option>', {
                    value: '',
                    text: 'Select staff'
                }));
                $.each(staffData, function(index, staff) {
                    staffSelect.append($('<option>', {
                        value: staff.id,
                        text: staff.name
                    }));
                });

                staffSelect.niceSelect('update');
            }
            //service & location wise staff dropdown end

            var daysOfWeek = '{{ json_encode($combinedArray) }}';
            var unavailableDates = {!! json_encode($businesholiday) !!};

            @if (module_is_active('FlexibleDays', $business->created_by) == true)
                function flexibleDaysDayOff(selectedStaff) {
                    if (selectedStaff) {
                        var csrfToken = $('meta[name="csrf-token"]').attr('content');
                        $('#loader').fadeIn();
                        $.ajax({
                            url: '{{ route('flexibledays.dayoffcheck') }}',
                            method: 'POST',
                            data: {
                                business: {{ $business->id }},
                                staff: selectedStaff
                            },
                            context: this,
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            success: function(response) {
                                $('#loader').fadeOut();

                                if (response.status == 'success') {
                                    var daysOfWeek = response.dayOffArray;

                                    $('#datepicker').datepicker('destroy');

                                    $('#datepicker').datepicker({
                                        startDate: '+0d',
                                        format: 'dd-mm-yyyy',
                                        autoclose: true,
                                        daysOfWeekDisabled: daysOfWeek,
                                        datesDisabled: unavailableDates
                                    });

                                } else {
                                    var daysOfWeek = '{{ json_encode($combinedArray) }}';
                                    $('#datepicker').datepicker('destroy');

                                    $('#datepicker').datepicker({
                                        startDate: '+0d',
                                        format: 'dd-mm-yyyy',
                                        autoclose: true,
                                        daysOfWeekDisabled: daysOfWeek,
                                        datesDisabled: unavailableDates
                                    });
                                }
                            }
                        });
                    }
                }
            @endif

            $('#datepicker').datepicker({
                startDate: '+0d',
                format: 'dd-mm-yyyy',
                autoclose: true,
                daysOfWeekDisabled: daysOfWeek,
                datesDisabled: unavailableDates
            });

            // timeSlot duration start
            $('#serviceSelect').change(function() {
                var selectedService = $(this).val();
                var selectedDate = $('#datepicker').val();
                appointmentTimeSlot(selectedService, selectedDate);
            });
            $('#staffSelect').change(function() {
                var selectedService = $('#serviceSelect').val();
                var selectedStaff = $(this).val();
                var selectedDate = $('#datepicker').val();
                @if (module_is_active('FlexibleDays', $business->created_by) == true)
                    flexibleDaysDayOff(selectedStaff);
                @endif
                appointmentTimeSlot(selectedService, selectedDate, selectedStaff);
            });
            $('#datepicker').on('changeDate', function() {
                var selectedService = $('#serviceSelect').val();
                var selectedStaff = $('#staffSelect').val();
                var selectedDate = $(this).val();
                @if (module_is_active('FlexibleDays', $business->created_by) == true)
                    flexibleDaysDayOff(selectedStaff);
                @endif
                appointmentTimeSlot(selectedService, selectedDate, selectedStaff);
            });

            @if (module_is_active('FlexibleDuration', $business->created_by) != true)
                function appointmentTimeSlot(selectedService, selectedDate, selectedStaff) {
                    if (selectedService && selectedDate) {
                        var csrfToken = $('meta[name="csrf-token"]').attr('content');
                        $('#loader').fadeIn();
                        $.ajax({
                            url: '{{ route('appointment.duration') }}',
                            method: 'POST',
                            data: {
                                service: selectedService,
                                staff: selectedStaff,
                                date: selectedDate
                            },
                            context: this,
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            success: function(response) {
                                $('#loader').fadeOut();
                                if (response.result == 'success') {
                                    // Handle the response from the server
                                    var timeSlots = response.timeSlots;
                                    // Display time slots below the datepicker
                                    var timeSlotsContainer = $('#timeSlotsContainer');
                                    timeSlotsContainer.empty(); // Clear previous time slots

                                    // Helper function to convert 24-hour time to 12-hour format
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

                                    if (timeSlots.length > 0) {

                                        timeSlots.forEach(function(timeSlot, index) {
                                            var newLiTag = $('<li class="checkbox-custom">');
                                            var input = $('<input type="radio">')
                                                .attr('id', 'radio' + index)
                                                .attr('name', 'duration')
                                                .attr('data-id', timeSlot.flexible_id)
                                                .attr('service-id', timeSlot.service_id)
                                                .attr('value', timeSlot.start + '-' + timeSlot
                                                    .end);
                                            if (timeSlot.flexible_id) {
                                                input.addClass('timeslot-flexible');
                                                input.attr('data-is', 'true');
                                            }
                                            input.addClass('timeslot');


                                            var label = $('<label>')
                                                .attr('for', 'radio' + index)
                                                .addClass('btn');

                                            if (timeSlot.flexible_id) {
                                                label.addClass('timeslot-flexible');
                                            }
                                            var svgIcon = $(
                                                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">\<g clip-path="url(#clip0_74_1151)">\<path d="M13.5634 11.7659L10.7748 9.67449V5.41426C10.7748 4.9859 10.4286 4.63965 10.0002 4.63965C9.57184 4.63965 9.22559 4.9859 9.22559 5.41426V10.0618C9.22559 10.3058 9.34023 10.5359 9.53543 10.6815L12.6338 13.0053C12.7732 13.1099 12.9359 13.1602 13.0978 13.1602C13.334 13.1602 13.5664 13.0541 13.7182 12.8496C13.9755 12.508 13.9057 12.0223 13.5634 11.7659Z" fill="#100F17"/>\<path d="M10 0C4.48566 0 0 4.48566 0 10C0 15.5143 4.48566 20 10 20C15.5143 20 20 15.5143 20 10C20 4.48566 15.5143 0 10 0ZM10 18.4508C5.34082 18.4508 1.54918 14.6592 1.54918 10C1.54918 5.34082 5.34082 1.54918 10 1.54918C14.66 1.54918 18.4508 5.34082 18.4508 10C18.4508 14.6592 14.6592 18.4508 10 18.4508Z" fill="#100F17"/>\</g>\</svg>'
                                            );
                                            var svgIcon = $(
                                                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">\
                                                                                                                                                                                            <g clip-path="url(#clip0_74_1151)">\
                                                                                                                                                                                                <path d="M13.5634 11.7659L10.7748 9.67449V5.41426C10.7748 4.9859 10.4286 4.63965 10.0002 4.63965C9.57184 4.63965 9.22559 4.9859 9.22559 5.41426V10.0618C9.22559 10.3058 9.34023 10.5359 9.53543 10.6815L12.6338 13.0053C12.7732 13.1099 12.9359 13.1602 13.0978 13.1602C13.334 13.1602 13.5664 13.0541 13.7182 12.8496C13.9755 12.508 13.9057 12.0223 13.5634 11.7659Z" fill="#100F17"/>\
                                                                                                                                                                                                <path d="M10 0C4.48566 0 0 4.48566 0 10C0 15.5143 4.48566 20 10 20C15.5143 20 20 15.5143 20 10C20 4.48566 15.5143 0 10 0ZM10 18.4508C5.34082 18.4508 1.54918 14.6592 1.54918 10C1.54918 5.34082 5.34082 1.54918 10 1.54918C14.66 1.54918 18.4508 5.34082 18.4508 10C18.4508 14.6592 14.6592 18.4508 10 18.4508Z" fill="#100F17"/>\
                                                                                                                                                                                            </g>\
                                                                                                                                                                                            </svg>'
                                            );

                                            // Format times to 12-hour format
                                            var time12Start = convertTo12Hour(timeSlot.start);
                                            var time12End = convertTo12Hour(timeSlot.end);
                                            var span = $('<span>').text(time12Start + ' to ' + time12End);

                                            // Append elements to label
                                            label.append(svgIcon);
                                            label.append(span);

                                            @if (module_is_active('WaitingList'))
                                                if (timeSlot.waiting_list && timeSlot
                                                    .waiting_list >
                                                    0) {
                                                    var waitingIcon = $('<span>').addClass(
                                                        'waiting-count').text(' (' +
                                                        timeSlot
                                                        .waiting_list + ')');
                                                    label.append(waitingIcon);
                                                }
                                            @endif

                                            // Append elements to li tag
                                            newLiTag.append(input);
                                            newLiTag.append(label);

                                            // Append new li tag to ul
                                            timeSlotsContainer.append(newLiTag);
                                        });
                                    } else {
                                        timeSlotsContainer.append('<p>No available time slots.</p>');
                                    }
                                }
                            }
                        });
                    }
                }
            @endif

            $('.tab-link').on('click', function() {
                var selectedTab = $(this).data('tab');
                $('#selected_tab').val(selectedTab);
            });

            var services = @json($services);
            // Team Booking
            $('.personSelect').on('change', function() {
                var selectedPersonCount = parseInt($('.personSelect').val());
                if (selectedPersonCount) {
                    var selectedServiceId = $('#serviceSelect').val();
                    var selectedService = services.find(service => service.id == selectedServiceId);
                    if (selectedService) {
                        var totalPrice = selectedService.price * selectedPersonCount;

                        $('#serviceAmount').html('Total Amount: ' + formatCurrency(totalPrice, '{{ $currency_setting }}'));

                    }
                }
            });
            // Bulk Appointments
            $('#quantitySelect').on('change', function() {
                var quantity = parseInt($(this).val());
                if (quantity) {
                    var selectedServiceId = $('#serviceSelect').val();
                    var selectedService = services.find(service => service.id == selectedServiceId);
                    if (selectedService) {
                        var totalPrice = selectedService.price * quantity;
                        $('#serviceAmount').html('Total Amount: ' + formatCurrency(totalPrice, '{{ $currency_setting }}'));



                    }
                }
            });

            $('#serviceSelect').on('change', function() {
                var selectedId = $(this).val();
                var selectedService = services.find(service => service.id == selectedId);
                var freeService = selectedService.is_free;
                if (freeService == '1') {
                    $('.payment-method-form').addClass('d-none');
                    $('.free-appointment').removeClass('d-none');
                    text =
                        "This service is completely free of charge! Simply click the Finish button to complete the process and book your service.";
                    $('.free-appointment').html(text);
                } else {
                    $('.free-appointment').addClass('d-none');
                    $('.payment-method-form').removeClass('d-none');

                    $('#serviceAmount').html('Total Amount: ' + formatCurrency(selectedService.price,
                        '{{ $currency_setting }}'));


                }
            });

            $('.payment_method').change(function() {
                var paymentMethod = $('input[name="payment_method"]:checked').data('payment');
                $('input[name="payment"]').val(paymentMethod);
            });

            $('#appointment-form').on('change', function(e) {
                var paymentAction = $('[data-payment-action]:checked').data("payment-action");
                action = '{{ route('appointment.form.submit') }}';
                if (paymentAction) {
                    action = paymentAction;
                    $("#appointment-form").attr("action", paymentAction);
                }
            });

            $(document).on('submit', '#appointment-form', function(e) {
                // Skip this handler if Formlayout11 is being used - it has its own submission handler
                var form = document.getElementById('appointment-form');
                if (form && form.dataset.fl11Handler === 'true') {
                    console.log('Parent layout: Skipping submit handler for Formlayout11');
                    return; // Let FL11 handler process the form
                }
                
                var payment_type = $(this).find('input[name="payment"]').val();
                if (payment_type == 'payhere') {
                    $('#appointment-form').submit();
                }
                if (payment_type == 'easebuzz') {
                    $('#appointment-form').submit();
                }
                e.preventDefault();

                var isValid = validateFinalStepBeforeSubmit(); // Validate fields in the selected tab
                if (!isValid) {
                    return;
                }

                var formData = new FormData(this);
                var customerTab = $('.tab-content.active').find('input');
                //flexible hour module logic
                $('.timeslot:checked').each(function() {
                    var parentLi = $(this).closest('li');
                    var isFlexible = parentLi.find('.timeslot-flexible').attr('data-is');
                    if (isFlexible === 'true') {
                        formData.append('flexible_id', $(this).attr('data-id'));
                    }
                });

                customerTab.each(function() {
                    var inputName = $(this).attr('name');
                    var inputValue = $(this).val();
                    formData.append(inputName, inputValue);
                });
                var customerTab = customerTab.serialize();
                var csrfToken = $('meta[name="csrf-token"]').attr('content');
                $('#loader').fadeIn();
                $.ajax({
                    url: action,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    success: function(response) {
                        $('#loader').fadeOut();
                        if (response.status == 'success') {
                            toastrs('Success', response.message, 'success');
                        } else {
                            toastrs('Error', response.message, 'error');
                        }
                        window.location.href = response.url;
                    }
                });
            });

            // book-appointment end
            var appointment_number = '{{ $appointment_number }}';
            if (appointment_number != null && appointment_number != '') {
                $('.stapes_status').addClass('active')
                $('.step-container').addClass('d-none')
                $('.step-container').last().removeClass('d-none')
                $('.step-container').last().addClass('active')
                $('.step-container').first().removeClass('active')
                if (appointment_number == 'failed') {
                    $('.step-container').first().hide();
                    text =
                        'Payment transaction unsuccessful. Please try again later or contact support for assistance.';
                    $('#appointment_number').html(text);
                    window.location.hash = '#appointment';
                } else {
                    $('.step-container').first().hide();
                    text = "Thank you! Your appointment booked successfully. Your appointment number is: " +
                        appointment_number;
                    $('#appointment_number').html(text);
                    window.location.hash = '#appointment';
                }
            }

            // step form validation start
            $(".step-container").on("click", ".next", function() {
                var buttonClicked = $(this);

                // Handle validation asynchronously
                validateCurrentStep(buttonClicked).then(function(isValid) {
                    if (!isValid) {
                        return false; // Stop the process if validation fails
                    }

                    // Move to the next step if validation succeeds
                    var nextStep = buttonClicked.parents(".step-container").next();
                    if (nextStep.hasClass('final_stepss')) {
                        $(".steps li").eq(buttonClicked.parents(".step-container").index() + 1)
                            .addClass(
                                "active");
                        $(".step-container").removeClass("active");
                        nextStep.addClass("active");
                    } else {
                        var nextStepIndex = buttonClicked.parents(".step-container").index() + 1;

                        if ($(".steps li").eq(nextStepIndex).attr('display') === 'true') {
                            $(".steps li").eq(nextStepIndex).addClass("active");
                            buttonClicked.parents(".step-container").removeClass("active").next()
                                .addClass(
                                    "active");
                        } else if ($(".steps li").eq(nextStepIndex).attr('display') === 'false') {
                            $(".steps li").eq(nextStepIndex).removeClass("active").next().addClass(
                                "active");
                            buttonClicked.parents(".step-container").removeClass("active").next()
                                .next()
                                .addClass("active");
                        } else {
                            $(".steps li").eq(nextStepIndex).addClass("active");
                            buttonClicked.parents(".step-container").removeClass("active").next()
                                .addClass(
                                    "active");
                        }
                    }
                });
            });

            var totalSteps = $(".steps li").length;
            $(".step-container").on("click", ".back", function() {
                var buttonClicked = $(this);

                if ($(".steps li").eq(buttonClicked.parents(".step-container").index() - 1).attr(
                        'display') == 'false') {
                    $(".steps li").eq(buttonClicked.parents(".step-container").index() - totalSteps)
                        .removeClass('active');
                    buttonClicked.parents(".step-container").removeClass("active").prev().prev().addClass(
                        "active");
                } else {
                    $(".steps li").eq($(this).parents(".step-container").index() - totalSteps).removeClass(
                        "active");
                    $(this).parents(".step-container").removeClass("active").prev().addClass("active");
                }
                // $('.right-slider').slick('refresh');
            });

            function validateCurrentStep(buttonClicked) {
                var currentStep = buttonClicked.parents(".step-container");
                var isAdditionalServicesActive = @json(module_is_active('AdditionalServices'));

                if (currentStep.hasClass('final_stepss')) {
                    var selectedTab = $(".tabs-wrapper .tabs .active").attr("data-tab");
                    return validateFinalStep(selectedTab).then(function(isValid) {
                        return isValid; // Return the result of the final step validation
                    });
                } else {
                    // Otherwise, validate fields for the current step
                    // var currentStepIndex = currentStep.index() + 1;
                    // if (currentStepIndex == "1") {
                    //     isValid = validateStep1();
                    // } else if (currentStepIndex == "2") {
                    //     isValid = validateStep2();
                    // }
                    var currentStepIndex = currentStep.index() + 1;
                    let isValid = true;

                    if (currentStepIndex == "1") {
                        isValid = validateStep1();
                    } else if (currentStepIndex == "2") {
                        if (isAdditionalServicesActive && currentStep.attr('id') === 'addition-services-section') {
                            return Promise.resolve(true);
                        }
                        isValid = validateStep2();
                    } else if (currentStepIndex == "3") {
                        if (isAdditionalServicesActive && currentStep.attr('id') === 'addition-services-section') {
                            return Promise.resolve(true);
                        }
                        isValid = validateStep2();
                    }
                    // Add more conditions for additional steps if needed
                    return Promise.resolve(isValid);
                }

            }

            function validateFinalStepBeforeSubmit() {
                var selectedTab = $(".tabs-wrapper .tabs .active").attr("data-tab");
                return validateFinalStep(selectedTab);
            }

            function validateFinalStep(selectedTab) {
                switch (selectedTab) {
                    case "new-user":
                        return Promise.resolve(validateNewUserFields());
                    case "existing-user":
                        return validateExistingUserFields();
                    case "guest-user":
                        var selectedPersonCount = parseInt($('.personSelect').val());
                        if (selectedPersonCount) {
                            var selectedServiceId = $('#serviceSelect').val();
                            var selectedService = services.find(service => service.id == selectedServiceId);
                            $('#serviceAmount').html('Total Amount: ' + formatCurrency(selectedService.price,
                            '{{ $currency_setting }}'));
                        }
                        return Promise.resolve(validateGuestUserFields());
                    default:
                        return Promise.resolve(true);
                }
            }

            function validateNewUserFields() {
                // Example validation for new user fields
                var name = $('#new-user #name').val();
                var email = $('#new-user #email').val();
                var password = $('#new-user #password').val();
                var contact = $('#new-user #contact').val().trim().substring(0, 14);

                var isValidContact = /^\+[1-9]\d{0,2}\d{1,15}$/.test(contact);

                if (!name || !email || !password || !contact || !isValidContact) {
                    alert('Please fill in all required fields for new user and enter a valid Contact Number.');
                    return false;
                }
                return true;
            }

            function validateExistingUserFields() {
                // Example validation for new user fields
                var email = $('#existing-user #email').val();
                var password = $('#existing-user #password').val();

                if (!email || !password) {
                    alert('Please fill in all required fields for existing user.');
                    return Promise.resolve(false);
                } else {
                    return checkUser(email, password);

                }
                // return true;
            }

            function checkUser(email, password) {
                $('#loader').fadeIn();
                return new Promise(function(resolve, reject) {

                    $.ajax({
                        url: '{{ route('check.user.data') }}',
                        method: 'POST',
                        data: {
                            email: email,
                            password: password
                        },
                        success: function(response) {
                            $('#loader').fadeOut();
                            if (response.status == 'error') {
                                alert(response.message);
                                resolve(false);
                            } else {
                                resolve(true);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#loader').fadeOut();
                            console.error('Error fetching staff data:', error);
                        }
                    });
                });
            }

            function validateGuestUserFields() {
                // Example validation for new user fields
                var name = $('#guest-user #name').val();
                var email = $('#guest-user #email').val();
                var contact = $('#guest-user #contact').val().trim().substring(0, 14);

                var isValidContact = /^\+[1-9]\d{0,2}\d{1,15}$/.test(contact);

                if (!name || !email || !contact || !isValidContact) {
                    alert('Please fill in all required fields for guest user and enter a valid Contact Number.');
                    return false;
                }
                return true;
            }

            function validateStep1() {
                var service = $('#serviceSelect').val();
                var staff = $('#staffSelect').val();
                var location = $('#locationSelect').val();
                var servicesdata = @json($services);
                var selectedService = servicesdata.find(servicesdata => servicesdata.id == service);

                if (selectedService?.service_type != 'collaborative') {
                    if (!service || !staff || !location) {
                        alert('Please select all required fields.');
                        return false;
                    }
                }

                isValid = true;
                <?php if(module_is_active('SequentialAppointment')): ?>
                $('[data-repeater-item]').each(function(index, element) {
                    var $repeaterItem = $(element);
                    var sequential_service = $repeaterItem.find('.sequential_service').val();
                    var sequential_location = $repeaterItem.find('.sequential_location').val();
                    var sequential_staff = $repeaterItem.find('.sequential_staff').val();

                    if (!sequential_service || !sequential_location || !sequential_staff) {
                        alert('Please select all required fields in repeater item');
                        isValid = false;
                        return false;
                    }
                });
                <?php endif; ?>

                return isValid;
            }

            function validateStep2() {
                var date = $('#datepicker').val();
                var timeslot = $('.timeslot').is(':checked');

                if (!date || !timeslot) {
                    alert('Please select all required field.');
                    return false;
                }
                return true;
            }

            // step form validation end

            // script for online booking option
            @if (module_is_active('ZoomMeeting') || module_is_active('GoogleMeet'))
               $(document).ready(function() {
                $('#serviceSelect').change(function() {
                    var serviceValue = $('#serviceSelect').val();
                    var businessSlug = "{{ $business->slug }}";
                    if (serviceValue && businessSlug) {
                        checkServiceOnlineMeeting(serviceValue, businessSlug);
                    } else {
                        console.warn('Business slug not found!');
                    }
                });

                function checkServiceOnlineMeeting(serviceId, businessSlug) {
                    const url = `{{ url('check-service-online-meeting-form-layout') }}/${businessSlug}`;
                    $('#loader').fadeIn();
                    $.ajax({
                        url: url,
                        method: 'GET',
                        data: {
                            service: serviceId,
                        },
                        success: function(response) {
                            $('#loader').fadeOut();
                            if (response.loadStep && response.html) {
                                $('#onlineAppointmentStep').css('display', '');
                                $('#onlineAppointmentStep').empty();
                                $('#online-meeting-section').empty();
                                $('#onlineAppointmentStep').html(response.loadStep);
                                $('#online-meeting-section').html(response.html);
                                $('#onlineAppointmentStep').attr('display', 'true');
                                $('#online-meeting-section').attr('display', 'true');
                            } else {
                                $('#onlineAppointmentStep').css('display', 'none');
                                $('#onlineAppointmentStep').empty();
                                $('#online-meeting-section').empty();
                                $('#onlineAppointmentStep').attr('display', 'false');
                                $('#online-meeting-section').attr('display', 'false');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#loader').fadeOut();
                            console.error('Error fetching service data:', error);
                        }
                    });
                }
            });
            @endif
            // end
            //script for additional services module
            @if (module_is_active('AdditionalServices'))
                $('#serviceSelect').change(function() {
                    var serviceValue = $('#serviceSelect').val();
                    let url = window.location.href;
                    var businessSlug = url.substring(url.lastIndexOf('/') + 1);
                    if (serviceValue) {
                        checkServiceAdditionalServices(serviceValue, businessSlug);
                    } else {
                        // $('#staffSelect').empty();
                    }
                });

                function checkServiceAdditionalServices(serviceId, businessSlug) {
                    const url = `{{ url('check-service-additional-service') }}/${businessSlug}`;
                    $('#loader').fadeIn();
                    $.ajax({
                        url: url,
                        method: 'GET',
                        data: {
                            service: serviceId,
                        },
                        success: function(response) {
                            $('#loader').fadeOut();
                            if (response.html && response.loadStep) {
                                $('#additionalServicesStep').css('display', '');
                                $('#additionalServicesStep').html(response.loadStep);
                                $('#addition-services-section').html(response.html);
                                $('#additionalServicesStep').attr('display', 'true');
                                $('#addition-services-section').attr('display', 'true');
                            } else {
                                $('#additionalServicesStep').css('display', 'none');
                                $('#additionalServicesStep').empty();
                                $('#addition-services-section').empty();
                                $('#additionalServicesStep').attr('display', 'false');
                                $('#addition-services-section').attr('display', 'false');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#loader').fadeOut();
                            toastrs('Error', xhr.responseJSON.errors, 'error');
                            console.error('Error fetching additional service data:', error);
                        }
                    });
                }
            @endif
            //end
        });
    </script>
    @if ($message = Session::get('success'))
        <script>
            toastrs('Success', '{!! $message !!}', 'success');
        </script>
    @endif
    @if ($message = Session::get('error'))
        <script>
            toastrs('Error', '{!! $message !!}', 'error');
        </script>
    @endif
    @stack('script')

    {{-- custom-js --}}
    @if (!empty($customJs))
        <script type="text/javascript">
            {!! htmlspecialchars_decode($customJs) !!}
        </script>
    @endif
</body>

</html>

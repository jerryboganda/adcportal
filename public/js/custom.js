/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 */

"use strict";

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    cache: false,
    complete: function () {

        $('[data-toggle="tooltip"]').tooltip();
    },
});

$(function () {
    if ($('.custom-scroll').length) {
        $(".custom-scroll").niceScroll();
        $(".custom-scroll-horizontal").niceScroll();
    }

    if ($('.activity-wrap').length) {
        $(".activity-wrap").niceScroll();
    }



});

function validation() {

    var forms = document.querySelectorAll('.needs-validation');

    Array.prototype.forEach.call(forms, function (form) {

        form.addEventListener('submit', function (event) {
            var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
            }
            if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }

            form.classList.add('was-validated');
        }, false);
    });
}

$(document).ready(function () {
    if ($(".pc-dt-simple").length > 0) {
        $($(".pc-dt-simple")).each(function (index, element) {
            var id = $(element).attr('id');
            const dataTable = new simpleDatatables.DataTable("#" + id);
        });
    }

    if ($(".needs-validation").length > 0) {
        validation();
    }

    common_bind();
    summernote();
    choices();

    $(document).on('changeDate', '#datepicker', function () {
        var selectedService = $('.service').val();
        updateAppointment(selectedService);
    });


    // for Choose file
    $(document).on('change', 'input[type=file]', function () {
        var fileclass = $(this).attr('data-filename');
        var finalname = $(this).val().split('\\').pop();
        $('.' + fileclass).html(finalname);
    });
});

function summernote() {
    if ($(".summernote").length > 0) {
        $($(".summernote")).each(function (index, element) {
            var id = $(element).attr('id');
            $('#' + id).summernote({
                placeholder: "Write Here… ",
                dialogsInBody: !0,
                tabsize: 2,
                minHeight: 200,
                maxHeight: 250,
                toolbar: [
                    ['style', ['style']],
                    ["font", ["bold", "italic", "underline", "clear", "strikethrough"]],
                    ['fontname', ['fontname']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                ]
            });
        });
    }
}

function toastrs(text, message, type) {
    var f = document.getElementById('liveToast');
    if (!f) { return; }
    var $t = $('#liveToast');
    $t.removeClass('bg-primary bg-danger bg-warning bg-info text-white');
    if (type === 'success') { $t.addClass('bg-primary text-white'); }
    else if (type === 'warning') { $t.addClass('bg-warning'); }
    else if (type === 'info') { $t.addClass('bg-info text-white'); }
    else { $t.addClass('bg-danger text-white'); }
    $('#liveToast .toast-body').html(message);
    new bootstrap.Toast(f).show();
}

$(document).on('click', 'a[data-ajax-popup="true"], button[data-ajax-popup="true"], div[data-ajax-popup="true"]', function () {
    var title = $(this).data('title');
    $('#commonModal .modal-dialog').removeClass('modal-sm modal-md modal-lg modal-xl modal-xxl');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');
    $("#commonModal .modal-title").html(title);
    $("#commonModal .modal-dialog").addClass('modal-' + size);
    $.ajax({
        url: url,
        beforeSend: function () {
            $(".loader-wrapper").removeClass('d-none');
        },
        success: function (data) {
            $(".loader-wrapper").addClass('d-none');
            $('#commonModal .body').html(data);
            $("#commonModal").modal('show');
            summernote();
            taskCheckbox();
            common_bind("#commonModal");
            validation();
            var daysOfWeek = $('#datepicker').attr('data-dates');
            var unavailableDates = $('#datepicker').attr('data-holiday');
            if (daysOfWeek != null) {
                $('#datepicker').datepicker({
                    startDate: '+0d',
                    format: 'dd-mm-yyyy',
                    autoclose: true,
                    daysOfWeekDisabled: daysOfWeek,
                    datesDisabled: unavailableDates

                });
            }
        },
        error: function (xhr) {
            $(".loader-wrapper").addClass('d-none');
            toastrs('Error', xhr.responseJSON.error, 'error')
        }
    });
});

$(document).on('click', 'a[data-ajax-popup-over="true"], button[data-ajax-popup-over="true"], div[data-ajax-popup-over="true"]', function () {

    var validate = $(this).attr('data-validate');
    var id = '';
    if (validate) {
        id = $(validate).val();
    }

    var title = $(this).data('title');
    var size = ($(this).data('size') == '') ? 'md' : $(this).data('size');
    var url = $(this).data('url');

    $("#commonModalOver .modal-title").html(title);
    $('#commonModalOver .modal-dialog').removeClass('modal-sm modal-md modal-lg modal-xl modal-xxl');
    $("#commonModalOver .modal-dialog").addClass('modal-' + size);

    $.ajax({
        url: url + '?id=' + id,
        beforeSend: function () {
            $(".loader-wrapper").removeClass('d-none');
        },
        success: function (data) {
            $(".loader-wrapper").addClass('d-none');
            $('#commonModalOver .body').html(data);
            $("#commonModalOver").modal('show');
            summernote();
            taskCheckbox();
            validation();
        },
        error: function (xhr) {
            $(".loader-wrapper").addClass('d-none');
            toastrs('Error', xhr.responseJSON.error, 'error')
        }
    });

});

function arrayToJson(form) {
    var data = $(form).serializeArray();
    var indexed_array = {};

    $.map(data, function (n, i) {
        indexed_array[n['name']] = n['value'];
    });

    return indexed_array;
}

$(document).on("submit", "#commonModalOver form", function (e) {
    e.preventDefault();
    var data = arrayToJson($(this));
    data.ajax = true;

    var url = $(this).attr('action');
    $.ajax({
        url: url,
        data: data,
        type: 'POST',
        success: function (data) {
            toastrs('Success', data.success, 'success');
            $(data.target).append('<option value="' + data.record.id + '">' + data.record.name + '</option>');
            $(data.target).val(data.record.id);
            $(data.target).trigger('change');
            $("#commonModalOver").modal('hide');


        },
        error: function (data) {
            data = data.responseJSON;
            toastrs('Error', data.error, 'error')
        }
    });
});
function common_bind(selector = "body") {
    var $datepicker = $(selector + ' .datepicker');
    if ($(".datepicker-input").length) {
        const d_disable = new Datepicker(document.querySelector('.datepicker-input'), {
            buttonClass: 'btn',
            autohide: true
        });

    }
    if ($(".flatpickr-time-input").length) {
        $(".flatpickr-time-input").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });
    }
    if ($(".flatpickr-input").length) {
        $(".flatpickr-input").flatpickr({
            enableTime: false,
            dateFormat: "Y-m-d",
        });
    }
    if ($(".multi-flatpickr-input").length) {
        $(".multi-flatpickr-input").flatpickr({
            mode: "multiple",
            enableTime: false,
            dateFormat: "Y-m-d",
        });
    }
    if ($(".pc-timepicker-2").length) {
        document.querySelector(".pc-timepicker-2").flatpickr({
            enableTime: true,
            noCalendar: true,
        });
    }
    if ($(".flatpickr-with-datetime").length) {
        $(".flatpickr-with-datetime").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });
    }
    if ($(".flatpickr-to-input").length) {
        $(".flatpickr-to-input").flatpickr({
            mode: "range",
            dateFormat: "Y-m-d",
        });
    }
    if ($(".custom-datepicker").length) {
        $('.custom-datepicker').daterangepicker({
            singleDatePicker: true,
            format: 'Y-MM',
            locale: {
                format: 'Y-MM'
            }
        });
    }

    if ($(".choices").length > 0) {
        $($(".choices")).each(function (index, element) {
            var id = $(element).attr('id');
            var searchEnabled = $(element).attr('searchEnabled');
            if (searchEnabled == undefined) {
                searchEnabled = false;
            }
            else if (searchEnabled == 'true') {
                searchEnabled = true;
            }
            else {
                searchEnabled = false;
            }
            if (id !== undefined) {
                var multipleCancelButton = new Choices(
                    '#' + id, {
                    loadingText: 'Loading...',
                    searchEnabled: searchEnabled,
                    removeItemButton: true,
                }
                );
            }
        });
    }

    if ($(".jscolor").length) {
        jscolor.installByClassName("jscolor");
    }
    if ($("[avatar]").length) {

        LetterAvatar.transform();
    }
}
function choices(id = null) {
    if ($(".choices").length > 0) {
        $($(".choices")).each(function (index, element) {
            if (id != null) {
                var id = id;
            }
            else {
                var id = $(element).attr('id');
            }

            if (id !== undefined) {
                var multipleCancelButton = new Choices(
                    '#' + id, {
                    removeItemButton: true,
                }
                );
            }
        });
    }
}
function common_bind_confirmation() {
    if ($("[data-confirm]").length) {

        $('[data-confirm]').each(function () {
            var me = $(this),
                me_data = me.data('confirm');

            me_data = me_data.split("|");
            me.fireModal({
                title: me_data[0],
                body: me_data[1],
                buttons: [
                    {
                        text: me.data('confirm-text-yes') || 'Yes',
                        class: 'btn btn-sm btn-danger rounded-pill',
                        handler: function () {
                            eval(me.data('confirm-yes'));
                        }
                    },
                    {
                        text: me.data('confirm-text-cancel') || 'Cancel',
                        class: 'btn btn-sm btn-secondary rounded-pill',
                        handler: function (modal) {
                            $.destroyModal(modal);
                            eval(me.data('confirm-no'));
                        }
                    }
                ]
            })
        });
    }
}
function JsSearchBox() {
    if ($(".js-searchBox").length) {
        $(".js-searchBox").each(function (index) {
            if ($(this).parent().find('.formTextbox').length == 0) {
                $(this).searchBox({ elementWidth: '250' });
            }
        });
    }
}
function taskCheckbox() {
    var checked = 0;
    var count = 0;
    var percentage = 0;

    count = $("#check-list input[type=checkbox]").length;
    checked = $("#check-list input[type=checkbox]:checked").length;
    percentage = parseInt(((checked / count) * 100), 10);
    if (isNaN(percentage)) {
        percentage = 0;
    }
    $(".custom-label").text(percentage + "%");
    $('#taskProgress').css('width', percentage + '%');


    $('#taskProgress').removeClass('bg-warning');
    $('#taskProgress').removeClass('bg-primary');
    $('#taskProgress').removeClass('bg-success');
    $('#taskProgress').removeClass('bg-danger');

    if (percentage <= 15) {
        $('#taskProgress').addClass('bg-danger');
    } else if (percentage > 15 && percentage <= 33) {
        $('#taskProgress').addClass('bg-warning');
    } else if (percentage > 33 && percentage <= 70) {
        $('#taskProgress').addClass('bg-primary');
    } else {
        $('#taskProgress').addClass('bg-success');
    }
}

(function ($, window, i) {
    // Bootstrap 4 Modal
    $.fn.fireModal = function (options) {
        var options = $.extend({
            size: 'modal-md',
            center: false,
            animation: true,
            title: 'Modal Title',
            closeButton: false,
            header: true,
            bodyClass: '',
            footerClass: '',
            body: '',
            buttons: [],
            autoFocus: true,
            created: function () {
            },
            appended: function () {
            },
            onFormSubmit: function () {
            },
            modal: {}
        }, options);
        this.each(function () {
            i++;
            var id = 'fire-modal-' + i,
                trigger_class = 'trigger--' + id,
                trigger_button = $('.' + trigger_class);
            $(this).addClass(trigger_class);
            // Get modal body
            let body = options.body;
            if (typeof body == 'object') {
                if (body.length) {
                    let part = body;
                    body = body.removeAttr('id').clone().removeClass('modal-part');
                    part.remove();
                } else {
                    body = '<div class="text-danger">Modal part element not found!</div>';
                }
            }
            // Modal base template
            var modal_template = '   <div class="modal' + (options.animation == true ? ' fade' : '') + '" tabindex="-1" role="dialog" id="' + id + '">  ' +
                '     <div class="modal-dialog ' + options.size + (options.center ? ' modal-dialog-centered' : '') + '" role="document">  ' +
                '       <div class="modal-content">  ' +
                ((options.header == true) ?
                    '         <div class="modal-header">  ' +
                    '           <h5 class="modal-title mx-auto">' + options.title + '</h5>  ' +
                    ((options.closeButton == true) ?
                        '           <button type="button" class="close" data-dismiss="modal" aria-label="Close">  ' +
                        '             <span aria-hidden="true">&times;</span>  ' +
                        '           </button>  '
                        : '') +
                    '         </div>  '
                    : '') +
                '         <div class="modal-body text-center text-dark">  ' +
                '         </div>  ' +
                (options.buttons.length > 0 ?
                    '         <div class="modal-footer mx-auto">  ' +
                    '         </div>  '
                    : '') +
                '       </div>  ' +
                '     </div>  ' +
                '  </div>  ';
            // Convert modal to object
            var modal_template = $(modal_template);
            // Start creating buttons from 'buttons' option
            var this_button;
            options.buttons.forEach(function (item) {
                // get option 'id'
                let id = "id" in item ? item.id : '';
                // Button template
                this_button = '<button type="' + ("submit" in item && item.submit == true ? 'submit' : 'button') + '" class="' + item.class + '" id="' + id + '">' + item.text + '</button>';
                // add click event to the button
                this_button = $(this_button).off('click').on("click", function () {
                    // execute function from 'handler' option
                    item.handler.call(this, modal_template);
                });
                // append generated buttons to the modal footer
                $(modal_template).find('.modal-footer').append(this_button);
            });
            // append a given body to the modal
            $(modal_template).find('.modal-body').append(body);
            // add additional body class
            if (options.bodyClass) $(modal_template).find('.modal-body').addClass(options.bodyClass);
            // add footer body class
            if (options.footerClass) $(modal_template).find('.modal-footer').addClass(options.footerClass);
            // execute 'created' callback
            options.created.call(this, modal_template, options);
            // modal form and submit form button
            let modal_form = $(modal_template).find('.modal-body form'),
                form_submit_btn = modal_template.find('button[type=submit]');
            // append generated modal to the body
            $("body").append(modal_template);
            // execute 'appended' callback
            options.appended.call(this, $('#' + id), modal_form, options);
            // if modal contains form elements
            if (modal_form.length) {
                // if `autoFocus` option is true
                if (options.autoFocus) {
                    // when modal is shown
                    $(modal_template).on('shown.bs.modal', function () {
                        // if type of `autoFocus` option is `boolean`
                        if (typeof options.autoFocus == 'boolean')
                            modal_form.find('input:eq(0)').focus(); // the first input element will be focused
                        // if type of `autoFocus` option is `string` and `autoFocus` option is an HTML element
                        else if (typeof options.autoFocus == 'string' && modal_form.find(options.autoFocus).length)
                            modal_form.find(options.autoFocus).focus(); // find elements and focus on that
                    });
                }
                // form object
                let form_object = {
                    startProgress: function () {
                        modal_template.addClass('modal-progress');
                    },
                    stopProgress: function () {
                        modal_template.removeClass('modal-progress');
                    }
                };
                // if form is not contains button element
                if (!modal_form.find('button').length) $(modal_form).append('<button class="d-none" id="' + id + '-submit"></button>');
                // add click event
                form_submit_btn.click(function () {
                    modal_form.submit();
                });
                // add submit event
                modal_form.submit(function (e) {
                    // start form progress
                    form_object.startProgress();
                    // execute `onFormSubmit` callback
                    options.onFormSubmit.call(this, modal_template, e, form_object);
                });
            }
            $(document).on("click", '.' + trigger_class, function () {
                $('#' + id).modal(options.modal);
                return false;
            });
        });
    }

    // Bootstrap Modal Destroyer
    $.destroyModal = function (modal) {
        modal.modal('hide');
        modal.on('hidden.bs.modal', function () {
        });
    }
})(jQuery, this, 0);

var Charts = (function () {
    // Variable
    var $toggle = $('[data-toggle="chart"]');
    var mode = 'light';//(themeMode) ? themeMode : 'light';
    var fonts = {
        base: 'Open Sans'
    }

    // Colors
    var colors = {
        gray: {
            100: '#f6f9fc',
            200: '#e9ecef',
            300: '#dee2e6',
            400: '#ced4da',
            500: '#adb5bd',
            600: '#8898aa',
            700: '#525f7f',
            800: '#32325d',
            900: '#212529'
        },
        theme: {
            'default': '#172b4d',
            'primary': '#5e72e4',
            'secondary': '#f4f5f7',
            'info': '#11cdef',
            'success': '#2dce89',
            'danger': '#f5365c',
            'warning': '#fb6340'
        },
        black: '#12263F',
        white: '#FFFFFF',
        transparent: 'transparent',
    };


    // Methods

    // Chart.js global options
    function chartOptions() {

        // Options
        var options = {
            defaults: {
                global: {
                    responsive: true,
                    maintainAspectRatio: false,
                    defaultColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontColor: (mode == 'dark') ? colors.gray[700] : colors.gray[600],
                    defaultFontFamily: fonts.base,
                    defaultFontSize: 13,
                    layout: {
                        padding: 0
                    },
                    legend: {
                        display: false,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 16
                        }
                    },
                    elements: {
                        point: {
                            radius: 0,
                            backgroundColor: colors.theme['primary']
                        },
                        line: {
                            tension: .4,
                            borderWidth: 4,
                            borderColor: colors.theme['primary'],
                            backgroundColor: colors.transparent,
                            borderCapStyle: 'rounded'
                        },
                        rectangle: {
                            backgroundColor: colors.theme['warning']
                        },
                        arc: {
                            backgroundColor: colors.theme['primary'],
                            borderColor: (mode == 'dark') ? colors.gray[800] : colors.white,
                            borderWidth: 4
                        }
                    },
                    tooltips: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                    }
                },
                doughnut: {
                    cutoutPercentage: 83,
                    legendCallback: function (chart) {
                        var data = chart.data;
                        var content = '';

                        data.labels.forEach(function (label, index) {
                            var bgColor = data.datasets[0].backgroundColor[index];

                            content += '<span class="chart-legend-item">';
                            content += '<i class="chart-legend-indicator" style="background-color: ' + bgColor + '"></i>';
                            content += label;
                            content += '</span>';
                        });

                        return content;
                    }
                }
            }
        }

        // yAxes
        Chart.scaleService.updateScaleDefaults('linear', {
            gridLines: {
                borderDash: [2],
                borderDashOffset: [2],
                color: (mode == 'dark') ? colors.gray[900] : colors.gray[300],
                drawBorder: false,
                drawTicks: false,
                drawOnChartArea: true,
                zeroLineWidth: 0,
                zeroLineColor: 'rgba(0,0,0,0)',
                zeroLineBorderDash: [2],
                zeroLineBorderDashOffset: [2]
            },
            ticks: {
                beginAtZero: true,
                padding: 10,
                callback: function (value) {
                    if (!(value % 10)) {
                        return value
                    }
                }
            }
        });

        // xAxes
        Chart.scaleService.updateScaleDefaults('category', {
            gridLines: {
                drawBorder: false,
                drawOnChartArea: false,
                drawTicks: false
            },
            ticks: {
                padding: 20
            },
            maxBarThickness: 10
        });

        return options;

    }

    // Parse global options
    function parseOptions(parent, options) {
        for (var item in options) {
            if (typeof options[item] !== 'object') {
                parent[item] = options[item];
            } else {
                parseOptions(parent[item], options[item]);
            }
        }
    }

    // Push options
    function pushOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].push(data);
                });
            } else {
                pushOptions(parent[item], options[item]);
            }
        }
    }

    // Pop options
    function popOptions(parent, options) {
        for (var item in options) {
            if (Array.isArray(options[item])) {
                options[item].forEach(function (data) {
                    parent[item].pop();
                });
            } else {
                popOptions(parent[item], options[item]);
            }
        }
    }

    // Toggle options
    function toggleOptions(elem) {
        var options = elem.data('add');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        if (elem.is(':checked')) {

            // Add options
            pushOptions($chart, options);

            // Update chart
            $chart.update();
        } else {

            // Remove options
            popOptions($chart, options);

            // Update chart
            $chart.update();
        }
    }

    // Update options
    function updateOptions(elem) {
        var options = elem.data('update');
        var $target = $(elem.data('target'));
        var $chart = $target.data('chart');

        // Parse options
        parseOptions($chart, options);

        // Toggle ticks
        toggleTicks(elem, $chart);

        // Update chart
        $chart.update();
    }



    // Toggle ticks
    function toggleTicks(elem, $chart) {

        if (elem.data('prefix') !== undefined || elem.data('prefix') !== undefined) {
            var prefix = elem.data('prefix') ? elem.data('prefix') : '';
            var suffix = elem.data('suffix') ? elem.data('suffix') : '';

            // Update ticks
            $chart.options.scales.yAxes[0].ticks.callback = function (value) {
                if (!(value % 10)) {
                    return prefix + value + suffix;
                }
            }

            // Update tooltips
            $chart.options.tooltips.callbacks.label = function (item, data) {
                var label = data.datasets[item.datasetIndex].label || '';
                var yLabel = item.yLabel;
                var content = '';

                if (data.datasets.length > 1) {
                    content += '<span class="popover-body-label mr-auto">' + label + '</span>';
                }

                content += '<span class="popover-body-value">' + prefix + yLabel + suffix + '</span>';
                return content;
            }

        }
    }

    $('.remove_workspace').click(function (event) {
        var form = $(this).closest("form");
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        })
        swalWithBootstrapButtons.fire({
            title: 'Are you sure?',
            text: "This action can not be undone. Do you want to continue?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        })
    });

    $(document).on('click', '.show_confirm', function () {
        var form = $(this).closest("form");
        var title = $(this).attr("data-confirm");
        var text = $(this).attr("data-text");
        if (title == '' || title == undefined) {
            title = "Are you sure?";

        }
        if (text == '' || text == undefined) {
            text = "This action can not be undone. Do you want to continue?";

        }
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false
        })
        swalWithBootstrapButtons.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        })
    });




    // Events

    // Parse global options
    if (window.Chart) {
        parseOptions(Chart, chartOptions());
    }

    // Toggle options
    $toggle.on({
        'change': function () {
            var $this = $(this);

            if ($this.is('[data-add]')) {
                toggleOptions($this);
            }
        },
        'click': function () {
            var $this = $(this);

            if ($this.is('[data-update]')) {
                updateOptions($this);
            }
        }
    });


    // Return

    return {
        colors: colors,
        fonts: fonts,
        mode: mode
    };

})();
function postAjax(url, data, cb) {
    var token = $('meta[name="csrf-token"]').attr('content');
    var jdata = { _token: token };

    for (var k in data) {
        jdata[k] = data[k];
    }

    $.ajax({
        type: 'POST',
        url: url,
        data: jdata,
        success: function (data) {
            if (typeof (data) === 'object') {
                cb(data);
            } else {
                cb(data);
            }
        },
    });
}

function deleteAjax(url, data, cb) {
    var token = $('meta[name="csrf-token"]').attr('content');
    var jdata = { _token: token };

    for (var k in data) {
        jdata[k] = data[k];
    }

    $.ajax({
        type: 'DELETE',
        url: url,
        data: jdata,
        success: function (data) {
            if (typeof (data) === 'object') {
                cb(data);
            } else {
                cb(data);
            }
        },
    });
}
// Import Data
function SetData(params, count = 0) {
    if (count < 8) {
        var process_area = document.getElementById("process_area");
        if (process_area) {
            $('#process_area').html(params);
        }
        else {
            setTimeout(function () {
                SetData(params, count + 1);
            }, 500);
        }
    }
    else {
        toastrs('Success', '{{ __("Something went wrong please try again!") }}', 'success');
    }
}

$(document).on('click', '#bank_transfer_payment_is_on', function () {
    if ($('#bank_transfer_payment_is_on').prop('checked')) {
        $(".bank_transfer_text").removeAttr("disabled");
    } else {
        $('.bank_transfer_text').attr("disabled", "disabled");
    }
});

$(document).on("click", ".is_disable", function () {
    var url = $('.business').attr('data-url');
    var id = $(this).attr('data-id');
    var name = $(this).attr('data-name');
    var company_id = $(this).attr('data-company');
    var is_disable = ($(this).is(':checked')) ? $(this).val() : 0;
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            "is_disable": is_disable,
            "id": id,
            "name": name,
            "company_id": company_id,
            "_token": csrfToken,
        },
        success: function (data) {
            if (data.success) {
                if (name == 'business') {
                    var container = document.getElementById('user_section_' + id);
                    var checkboxes = container.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(function (checkbox) {
                        if (is_disable == 0) {
                            checkbox.disabled = true;
                            checkbox.checked = false;
                        } else {
                            checkbox.disabled = false;
                        }
                    });

                }
                $('.active_business').text(data.business_data.active_business);
                $('.disable_business').text(data.business_data.disable_business);
                $('.total_business').text(data.business_data.total_business);
                $.each(data.users_data, function (businessName, userData) {
                    var $businessElements = $('.business[data-business-id="' + userData.business_id + '"]');
                    // Update total_users, active_users, and disable_users for each business
                    $businessElements.find('.total_users').text(userData.total_users);
                    $businessElements.find('.active_users').text(userData.active_users);
                    $businessElements.find('.disable_users').text(userData.disable_users);
                });

                toastrs('success', data.success, 'success');
            } else {
                toastrs('error', data.error, 'error');

            }

        }
    });
});



function check_theme(color_val) {
    $('input[value="' + color_val + '"]').prop('checked', true);
    $('a[data-value]').removeClass('active_color');
    $('a[data-value="' + color_val + '"]').addClass('active_color');
}

document.addEventListener("DOMContentLoaded", function () {
    setTimeout(() => {
        var isActive = $('.small-title');
        if (isActive.length != '0') {
            var themescolors = document.querySelectorAll(".themes-color > a");
            for (var h = 0; h < themescolors.length; h++) {
                var c = themescolors[h];

                c.addEventListener("click", function (event) {
                    var targetElement = event.target;
                    if (targetElement.tagName == "SPAN") {
                        targetElement = targetElement.parentNode;
                    }
                    var temp = targetElement.getAttribute("data-value");
                    removeClassByPrefix(document.querySelector("body"), "theme-");
                    document.querySelector("body").classList.add(temp);
                });
            }


            if ($('#useradd-sidenav').length > 0) {
                var scrollSpy = new bootstrap.ScrollSpy(document.body, {
                    target: '#useradd-sidenav',
                    offset: 300,
                });
            }
            $(document).on('change', '#defult_currancy', function () {
                var sy = $('#defult_currancy option:selected').attr('data-symbol');
                $('#defult_currancy_symbol').val(sy);

            });

            var custdarklayout = document.querySelector("#cust-darklayout");
            var logo_dark = $('#pre_default_logo').attr('src');
            var logo_light = $('#landing_page_logo').attr('src');

            custdarklayout.addEventListener("click", function () {
                var mainStyleLink = document.querySelector("#style-link");
                var styleDark = mainStyleLink.getAttribute("data-style-dark");
                var styleLight = mainStyleLink.getAttribute("data-style-light");

                if (custdarklayout.checked) {
                    document.querySelector(".m-header > .b-brand > .logo-lg").setAttribute("src", logo_light);
                    document.querySelector("#main-style-link").setAttribute("href", styleDark);
                } else {
                    document.querySelector(".m-header > .b-brand > .logo-lg").setAttribute("src", logo_dark);
                    document.querySelector("#main-style-link").setAttribute("href", styleLight);
                }
            });

            if ($('#site_transparent').length > 0) {
                var custthemebg = document.querySelector("#site_transparent");
                custthemebg.addEventListener("click", function () {
                    if (custthemebg.checked) {
                        document.querySelector(".dash-sidebar").classList.add("transprent-bg");
                        document
                            .querySelector(".dash-header:not(.dash-mob-header)")
                            .classList.add("transprent-bg");
                    } else {
                        document.querySelector(".dash-sidebar").classList.remove("transprent-bg");
                        document
                            .querySelector(".dash-header:not(.dash-mob-header)")
                            .classList.remove("transprent-bg");
                    }
                });
            }

        }

    }, 1000);
});

function removeClassByPrefix(node, prefix) {
    for (let i = 0; i < node.classList.length; i++) {
        let value = node.classList[i];
        if (value.startsWith(prefix)) {
            node.classList.remove(value);
        }
    }
}



$(document).on('change', '#service', function () {
    var selectedService = $(this).val();
    updateAppointment(selectedService);
});
function updateAppointment(selectedService) {
    var selectedService = selectedService;
    var selectedStaff = $('#staff').val();
    var selectedDate = $('#datepicker').val();
    var url = $('#appointment-form-date').data('url');
    // Make an AJAX call
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    $.ajax({
        url: url, // Replace with your actual AJAX endpoint
        method: 'POST',
        data: {
            service: selectedService,
            date: selectedDate,
            staff: selectedStaff
            // Add other data if needed
        },
        context: this,
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        success: function (response) {
            if (response.result == 'success') {
                // Handle the response from the server
                var timeSlots = response.timeSlots;
                // Display time slots below the datepicker
                var timeSlotsContainer = $('#timeSlotsContainer');
                timeSlotsContainer.empty(); // Clear previous time slots

                if (timeSlots.length > 0) {
                    var timeSlotsList = $('<ul>');

                    timeSlots.forEach(function (timeSlot, index) {

                        var timeSlotLabel = $('<label>');
                        var radioInput = $('<input type="radio">')
                            .attr('name', 'duration')
                            .attr('data-id', timeSlot.flexible_id)
                            .attr('service-id', timeSlot.service_id)
                            .attr('value', timeSlot.start + '-' + timeSlot.end)
                            .attr('id', 'radio' + index);
                        radioInput.attr('data-is', 'true');
                        if (timeSlot.flexible_id) {
                            radioInput.addClass('timeslot-flexible');
                        };

                        var timeSlotText = $('<span>').text(' ' + timeSlot.start + '-' +
                            timeSlot.end);
                        timeSlotLabel.append(radioInput);
                        timeSlotLabel.append(timeSlotText);

                        timeSlotsList.append($('<li>').append(timeSlotLabel));
                    });

                    timeSlotsContainer.append(timeSlotsList);
                } else {
                    timeSlotsContainer.append('<p>No available time slots.</p>');
                }
            }
        }
    });
}

function decodeHtmlEntities(str) {
    const txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
}
function formatCurrency(price, settingsEntity) {
    let symbolPosition = 'pre';
    let currencySpace = null;
    let symbol = '$';
    let format = 2;
    let decimalSeparator = ',';
    let thousandSeparator = '.';

    const decodedString = decodeHtmlEntities(settingsEntity);
    const settings = JSON.parse(decodedString);
    price = parseFloat(price);
    if (isNaN(price)) {
        console.error('Invalid price value');
        return '';
    }

    let length = price.toFixed(format).split('.')[0].length;
    if (settings) {
        if (settings.site_currency_symbol_position === 'post') {
            symbolPosition = 'post';
        }
        if (settings.defult_currancy_symbol) {
            symbol = settings.defult_currancy_symbol;
        }
        if (settings.currency_format) {
            format = parseInt(settings.currency_format, 10);
        }
        if (settings.currency_space) {
            currencySpace = settings.currency_space;
        }
        if (settings.site_currency_symbol_name) {
            symbol = settings.site_currency_symbol_name === 'symbol' ? settings.defult_currancy_symbol : settings.defult_currancy;
        }

        if (length > 3) {
            decimalSeparator = settings.float_number && settings.float_number !== 'dot' ? ',' : '.';
        } else {
            decimalSeparator = settings.decimal_separator && settings.decimal_separator !== 'dot' ? ',' : '.';
        }
        thousandSeparator = settings.thousand_separator === 'dot' ? '.' : ',';
    }

    let [integerPart, fractionalPart] = price.toFixed(format).split('.');
    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

    let formattedPrice = integerPart + (fractionalPart ? decimalSeparator + fractionalPart : '');

    return (
        (symbolPosition === 'pre' ? symbol : '') +
        (currencySpace === 'withspace' ? ' ' : '') +
        formattedPrice +
        (currencySpace === 'withspace' ? ' ' : '') +
        (symbolPosition === 'post' ? symbol : '')
    );
}

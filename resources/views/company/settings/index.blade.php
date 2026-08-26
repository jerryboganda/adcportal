<!--Brand Settings-->

@php
    $successMessage = session()->get('success_message');
    $errorMessage = session()->get('error_message');
    if ($successMessage) {
        session()->forget('success_message');
    }
    if ($errorMessage) {
        session()->forget('error_message');
    }
@endphp

@if ($successMessage || $errorMessage)
<script>
    @if ($successMessage)
    toastrs('Success', '{{ $successMessage }}', 'success');
    @endif
    @if ($errorMessage)
    toastrs('Error', '{{ $errorMessage }}', 'error');
    @endif
</script>
@endif

<div id="site-settings" class="">
    {{ Form::open(['route' => ['company.settings.save'], 'enctype' => 'multipart/form-data', 'id' => 'setting-form']) }}
    @method('post')
    <div class="card">
        <div class="card-header p-3">
            <h5>{{ __('Brand Settings') }}</h5>
        </div>
        <div class="card-body px-3">
            <div class="row row-gaps">
                <div class="col-lg-4 col-12 d-flex">
                    <div class="card w-100">
                        <div class="card-header p-3">
                            <h5 class="small-title">{{ __('Logo Dark') }}</h5>
                        </div>
                        <div class="card-body setting-card setting-logo-box p-3">
                            <div class="d-flex flex-column justify-content-between align-items-center h-100">
                                <div class="logo-content img-fluid logo-set-bg  text-center py-2">
                                    @php
                                        $logo_dark = isset($settings['logo_dark'])
                                            ? (check_file($settings['logo_dark'])
                                                ? $settings['logo_dark']
                                                : 'uploads/logo/logo_dark.png')
                                            : 'uploads/logo/logo_dark.png';
                                    @endphp
                                    <img alt="image" src="{{ get_file($logo_dark) }}{{ '?' . time() }}"
                                        class="small-logo" id="pre_default_logo">
                                </div>
                                <div class="choose-files mt-3">
                                    <label for="logo_dark">
                                        <div class=" bg-primary "> <i
                                                class="ti ti-upload px-1"></i>{{ __('Choose file here') }}</div>
                                        <input type="file" class="form-control file" name="logo_dark" id="logo_dark"
                                            data-filename="logo_dark"
                                            onchange="document.getElementById('pre_default_logo').src = window.URL.createObjectURL(this.files[0])">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-12 d-flex">
                    <div class="card w-100">
                        <div class="card-header p-3">
                            <h5 class="small-title">{{ __('Logo Light') }}</h5>
                        </div>
                        <div class="card-body setting-card setting-logo-box p-3">
                            <div class="d-flex flex-column justify-content-between align-items-center h-100">
                                <div class="logo-content img-fluid logo-set-bg text-center py-2">
                                    @php
                                        $logo_light = isset($settings['logo_light'])
                                            ? (check_file($settings['logo_light'])
                                                ? $settings['logo_light']
                                                : 'uploads/logo/logo_light.png')
                                            : 'uploads/logo/logo_light.png';
                                    @endphp
                                    <img alt="image" src="{{ get_file($logo_light) }}{{ '?' . time() }}"
                                        class="img_setting small-logo" id="landing_page_logo">
                                </div>
                                <div class="choose-files mt-3">
                                    <label for="logo_light">
                                        <div class=" bg-primary "> <i
                                                class="ti ti-upload px-1"></i>{{ __('Choose file here') }}</div>
                                        <input type="file" class="form-control file" name="logo_light"
                                            id="logo_light" data-filename="logo_light"
                                            onchange="document.getElementById('landing_page_logo').src = window.URL.createObjectURL(this.files[0])">

                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-12 d-flex">
                    <div class="card w-100">
                        <div class="card-header p-3">
                            <h5 class="small-title">{{ __('Favicon') }}</h5>
                        </div>
                        <div class="card-body setting-card setting-logo-box p-3">
                            <div class="d-flex flex-column justify-content-between align-items-center h-100">
                                <div class="logo-content img-fluid logo-set-bg text-center py-2">
                                    @php
                                        $favicon = isset($settings['favicon'])
                                            ? (check_file($settings['favicon'])
                                                ? $settings['favicon']
                                                : 'uploads/logo/favicon.png')
                                            : 'uploads/logo/favicon.png';
                                    @endphp
                                    <img src="{{ get_file($favicon) }}{{ '?' . time() }}" class="setting-img"
                                        width="40px" id="img_favicon" />
                                </div>
                                <div class="choose-files mt-3">
                                    <label for="favicon">
                                        <div class=" bg-primary "> <i
                                                class="ti ti-upload px-1"></i>{{ __('Choose file here') }}</div>
                                        <input type="file" class="form-control file" name="favicon" id="favicon"
                                            data-filename="favicon"
                                            onchange="document.getElementById('img_favicon').src = window.URL.createObjectURL(this.files[0])">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4 row-gap setting-box">
                <div class="col-sm-6 col-12">
                    <div class="form-group d-flex align-items-center gap-2 mb-0">
                        <label for="title_text" class="form-label mb-0">{{ __('Title Text') }}</label>
                        {{ Form::text('title_text', !empty($settings['title_text']) ? $settings['title_text'] : null, ['class' => 'form-control', 'placeholder' => __('Enter Title Text')]) }}
                    </div>
                </div>
                <div class="col-sm-6 col-12">
                    <div class="form-group d-flex align-items-center gap-2 mb-0">
                        <label for="footer_text" class="form-label mb-0">{{ __('Footer Text') }}</label>
                        {{ Form::text('footer_text', !empty($settings['footer_text']) ? $settings['footer_text'] : null, ['class' => 'form-control', 'placeholder' => __('Enter Footer Text')]) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body border-1 border-top  px-3">
            <div class="setting-card setting-logo-box">
                <h4 class="small-title">{{ __('Theme Customizer') }}</h4>
                <div class="row row-gap">
                    <div class="col-xxl-3 col-md-4 col-sm-6 col-12">
                        <div class="card h-100 mb-0">
                            <div class="card-header p-2">
                                <h6 class="">
                                    <i class="ti ti-credit-card me-2"></i>{{ __('Primary color settings') }}
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="color-wrp">
                                    <div class="theme-color themes-color">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-1' ? 'active_color' : '' }}"
                                            data-value="theme-1" onclick="check_theme('theme-1')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-1' ? 'checked' : '' }}
                                            name="color" value="theme-1">

                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-2' ? 'active_color' : '' }} "
                                            data-value="theme-2" onclick="check_theme('theme-2')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-2' ? 'checked' : '' }}
                                            name="color" value="theme-2">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-3' ? 'active_color' : '' }}"
                                            data-value="theme-3" onclick="check_theme('theme-3')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-3' ? 'checked' : '' }}
                                            name="color" value="theme-3">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-4' ? 'active_color' : '' }}"
                                            data-value="theme-4" onclick="check_theme('theme-4')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-4' ? 'checked' : '' }}
                                            name="color" value="theme-4">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-5' ? 'active_color' : '' }}"
                                            data-value="theme-5" onclick="check_theme('theme-5')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-5' ? 'checked' : '' }}
                                            name="color" value="theme-5">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-6' ? 'active_color' : '' }}"
                                            data-value="theme-6" onclick="check_theme('theme-6')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-6' ? 'checked' : '' }}
                                            name="color" value="theme-6">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-7' ? 'active_color' : '' }}"
                                            data-value="theme-7" onclick="check_theme('theme-7')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-7' ? 'checked' : '' }}
                                            name="color" value="theme-7">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-8' ? 'active_color' : '' }}"
                                            data-value="theme-8" onclick="check_theme('theme-8')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-8' ? 'checked' : '' }}
                                            name="color" value="theme-8">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-9' ? 'active_color' : '' }}"
                                            data-value="theme-9" onclick="check_theme('theme-9')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-9' ? 'checked' : '' }}
                                            name="color" value="theme-9">
                                        <a href="#!"
                                            class="themes-color-change{{ isset($settings['color']) && $settings['color'] == 'theme-10' ? 'active_color' : '' }}"
                                            data-value="theme-10" onclick="check_theme('theme-10')"></a>
                                        <input type="radio" class="d-none"
                                            {{ isset($settings['color']) && $settings['color'] == 'theme-10' ? 'checked' : '' }}
                                            name="color" value="theme-10">
                                        <div class="color-picker-wrp ">
                                            <input type="color"
                                                value="{{ isset($settings['color']) ? $settings['color'] : '' }}"
                                                class="colorPicker m-0 {{ isset($settings['color_flag']) && $settings['color_flag'] == 'true' ? 'active_color' : '' }}"
                                                name="custom_color" id="color-picker">
                                            <input type='hidden' name="color_flag"
                                                value={{ isset($settings['color_flag']) && $settings['color_flag'] == 'true' ? 'true' : 'false' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-4 col-sm-6 col-12">
                        <div class="card h-100 mb-0">
                            <div class="card-header p-2">
                                <h6>
                                    <i class="ti ti-layout me-2"></i> {{ __('Sidebar settings') }}
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="form-check form-switch d-flex gap-2 flex-column p-0">
                                    <label class="form-check-label f-w-600 pl-1"
                                        for="site_transparent">{{ __('Transparent layout') }}</label>
                                    <input type="checkbox" class="form-check-input ms-0" id="site_transparent"
                                        name="site_transparent"
                                        {{ isset($settings['site_transparent']) && $settings['site_transparent'] == 'on' ? 'checked' : '' }} />

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-4 col-sm-6 col-12">
                        <div class="card h-100 mb-0">
                            <div class="card-header p-2">
                                <h6 class="">
                                    <i class="ti ti-sun me-2"></i>{{ __('Layout settings') }}
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="form-check form-switch d-flex gap-2 flex-column p-0" id="style-link"
                                    data-style-dark="{{ asset('assets/css/style-dark.css') }}"
                                    data-style-light="{{ asset('assets/css/style.css') }}">

                                    <label class="form-check-label f-w-600 pl-1"
                                        for="cust-darklayout">{{ __('Dark Layout') }}</label>
                                    <input type="checkbox" class="form-check-input ms-0" id="cust-darklayout"
                                        name="cust_darklayout"
                                        {{ isset($settings['cust_darklayout']) && $settings['cust_darklayout'] == 'on' ? 'checked' : '' }} />

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-4 col-sm-6 col-12">
                        <div class="card h-100 mb-0">
                            <div class="card-header p-2">
                                <h6 class="">
                                    <i class="ti ti-align-right me-2 h5"></i>{{ __('Enable RTL') }}
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="form-check form-switch d-flex gap-2 flex-column p-0">
                                    <label class="form-check-label f-w-600 pl-1"
                                        for="site_rtl">{{ __('RTL Layout') }}</label>
                                    <input type="checkbox" class="form-check-input ms-0" id="site_rtl"
                                        name="site_rtl"
                                        {{ isset($settings['site_rtl']) && $settings['site_rtl'] == 'on' ? 'checked' : '' }} />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end p-3">
            <input class="btn btn-print-invoice  btn-primary " type="submit" value="{{ __('Save Changes') }}">
        </div>
        {{ Form::close() }}
    </div>
    <!--system settings-->
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="card" id="system-settings">
                <div class="card-header p-3">
                    <h5 class="small-title">{{ __('System Settings') }}</h5>
                </div>
                {{ Form::open(['route' => ['company.system.setting.store'], 'id' => 'setting-system-form']) }}
                @method('post')
                <div class="card-body p-3 pb-0">
                    <div class="row">

                        <div class="col-sm-6 col-12">
                            <div class="form-group col switch-width">
                                {{ Form::label('defult_timezone', __('Default Timezone'), ['class' => ' col-form-label pt-0']) }}
                                {{ Form::select('defult_timezone', $timezones, isset($settings['defult_timezone']) ? $settings['defult_timezone'] : null, ['id' => 'timezone', 'class' => 'form-control choices', 'searchEnabled' => 'true']) }}
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="form-group col switch-width">
                                {{ Form::label('defult_language', __('Default Language'), ['class' => ' col-form-label pt-0']) }}
                                <select class="form-control" data-trigger name="defult_language" id="defult_language"
                                    placeholder="This is a search placeholder">
                                    @foreach (languages() as $key => $language)
                                        <option value="{{ $key }}"
                                            {{ isset($settings['defult_language']) && $settings['defult_language'] == $key ? 'selected' : '' }}>
                                            {{ Str::ucfirst($language) }} </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-6 col-12">
                            <div class="form-group col switch-width">
                                <label for="site_date_format"
                                    class="col-form-label pt-0">{{ __('Date Format') }}</label>
                                <select type="text" name="site_date_format" class="form-control selectric"
                                    id="site_date_format">
                                    <option value="d-m-Y"
                                        @if (isset($settings['site_date_format']) && $settings['site_date_format'] == 'd-m-Y') selected="selected" @endif>
                                        DD-MM-YYYY</option>
                                    <option value="m-d-Y"
                                        @if (isset($settings['site_date_format']) && $settings['site_date_format'] == 'm-d-Y') selected="selected" @endif>
                                        MM-DD-YYYY</option>
                                    <option value="Y-m-d"
                                        @if (isset($settings['site_date_format']) && $settings['site_date_format'] == 'Y-m-d') selected="selected" @endif>
                                        YYYY-MM-DD</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="form-group col switch-width">
                                <label for="site_time_format"
                                    class="col-form-label pt-0">{{ __('Time Format') }}</label>
                                <select type="text" name="site_time_format" class="form-control selectric"
                                    id="site_time_format">
                                    <option value="g:i A"
                                        @if (isset($settings['site_time_format']) && $settings['site_time_format'] == 'g:i A') selected="selected" @endif>
                                        10:30 PM</option>
                                    <option value="H:i"
                                        @if (isset($settings['site_time_format']) && $settings['site_time_format'] == 'H:i') selected="selected" @endif>
                                        22:30</option>
                                </select>
                            </div>
                        </div>


                        <div class="col-sm-6 col-12">
                            <div class="form-group">
                                <div class="form-group">
                                    {{ Form::label('appointment_prefix', __('Appointment Prefix'), ['class' => 'form-label']) }}
                                    {{ Form::text('appointment_prefix', !empty($settings['appointment_prefix']) ? $settings['appointment_prefix'] : '#APP00000', ['class' => 'form-control', 'placeholder' => 'Enter Appointment Prefix']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end p-3">
                    <input class="btn btn-print-invoice  btn-primary " type="submit"
                        value="{{ __('Save Changes') }}">
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>

    <!--currency settings-->
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="card" id="currency-settings">
                <div class="card-header p-3">
                    <h5 class="small-title">{{ __('Currency Settings') }}</h5>
                </div>
                {{ Form::open(['route' => ['super.admin.currency.settings'], 'method' => 'post', 'id' => 'setting-currency-form']) }}
                <div class="card-body p-3 pb-0">
                    <div class="row">
                        <div class="col-xxl-4 col-sm-6">
                            <div class="form-group col switch-width">
                                {{ Form::label('currency_format', __('Decimal Format'), ['class' => ' col-form-label']) }}
                                <select class="form-control currency_note" data-trigger name="currency_format"
                                    id="currency_format" placeholder="This is a search placeholder">
                                    <option value="0"
                                        {{ isset($settings['currency_format']) && $settings['currency_format'] == '0' ? 'selected' : '' }}>
                                        1</option>
                                    <option value="1"
                                        {{ isset($settings['currency_format']) && $settings['currency_format'] == '1' ? 'selected' : '' }}>
                                        1.0</option>
                                    <option value="2"
                                        {{ isset($settings['currency_format']) && $settings['currency_format'] == '2' ? 'selected' : '' }}>
                                        1.00</option>
                                    <option value="3"
                                        {{ isset($settings['currency_format']) && $settings['currency_format'] == '3' ? 'selected' : '' }}>
                                        1.000</option>
                                    <option value="4"
                                        {{ isset($settings['currency_format']) && $settings['currency_format'] == '4' ? 'selected' : '' }}>
                                        1.0000</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="form-group col switch-width">
                                {{ Form::label('defult_currancy', __('Default Currancy'), ['class' => ' col-form-label']) }}
                                <select class="form-control currency_note" data-trigger name="defult_currancy"
                                    id="defult_currancy" placeholder="This is a search placeholder">
                                    @foreach (currency() as $c)
                                        <option value="{{ $c->symbol }}-{{ $c->code }}"
                                            data-symbol="{{ $c->symbol }}"
                                            {{ isset($settings['defult_currancy']) && $settings['defult_currancy'] == $c->code ? 'selected' : '' }}>
                                            {{ $c->symbol }} - {{ $c->code }} </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="form-group col switch-width">
                                <label for="float_number" class="col-form-label">{{ __('Float Number') }}</label>
                                <select type="text" name="float_number"
                                    class="form-control selectric currency_note" id="float_number">
                                    <option value="comma"
                                        @if (@$settings['float_number'] == 'comma') selected="selected" @endif>
                                        {{ __('Comma') }}</option>
                                    <option value="dot"
                                        @if (@$settings['float_number'] == 'dot') selected="selected" @endif>
                                        {{ __('Dot') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="form-group col switch-width">
                                <label for="decimal_separator"
                                    class="col-form-label">{{ __('Decimal Separator') }}</label>
                                <select type="text" name="decimal_separator"
                                    class="form-control selectric currency_note" id="decimal_separator">
                                    <option value="dot"
                                        @if (@$settings['decimal_separator'] == 'dot') selected="selected" @endif>
                                        {{ __('Dot') }}</option>
                                    <option value="comma"
                                        @if (@$settings['decimal_separator'] == 'comma') selected="selected" @endif>
                                        {{ __('Comma') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="form-group col switch-width">
                                <label for="thousand_separator"
                                    class="col-form-label">{{ __('Thousands Separator') }}</label>
                                <select type="text" name="thousand_separator"
                                    class="form-control selectric currency_note" id="thousand_separator">
                                    <option value="dot"
                                        @if (@$settings['thousand_separator'] == 'dot') selected="selected" @endif>
                                        {{ __('Dot') }}</option>
                                    <option value="comma"
                                        @if (@$settings['thousand_separator'] == 'comma') selected="selected" @endif>
                                        {{ __('Comma') }}</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <div class="row">
                        <div class="col-xxl-4 col-sm-6">
                            <div class="card">
                                <div class="card-header p-2 ">
                                    {{ Form::label('currency_space', __('Currency Symbol Space'), ['class' => 'col-form-label p-0 form-label h6 mb-0']) }}
                                </div>
                                <div class="card-body p-2">
                                    <div class="form-group col switch-width mb-0">

                                        <div class="form-check mb-2">
                                            <input class="form-check-input currency_note pointer" type="radio"
                                                name="currency_space" value="withspace"
                                                @if (!isset($settings['currency_space']) || $settings['currency_space'] == 'withspace') checked @endif
                                                id="flexCheckDefault">
                                            <label class="form-check-label" for="flexCheckDefault">
                                                {{ __('With space') }}
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input currency_note pointer" type="radio"
                                                name="currency_space" value="withoutspace"
                                                @if (!isset($settings['currency_space']) || $settings['currency_space'] == 'withoutspace') checked @endif
                                                id="flexCheckChecked">
                                            <label class="form-check-label" for="flexCheckChecked">
                                                {{ __('Without space') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @error('currency_space')
                                    <span class="invalid-currency_space" role="alert">
                                        <strong class="text-danger">{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="card">
                                <div class="card-header p-2">
                                    <label class="form-label col-form-label h6 mb-0 p-0"
                                        for="example3cols3Input">{{ __('Currency Symbol Position') }}</label>
                                </div>
                                <div class="card-body p-2">
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                                        <div class="form-group col switch-width mb-0">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input currency_note" type="radio"
                                                    name="site_currency_symbol_position" value="pre"
                                                    @if (!isset($settings['site_currency_symbol_position']) || $settings['site_currency_symbol_position'] == 'pre') checked @endif
                                                    id="flexCheckDefault">
                                                <label class="form-check-label" for="flexCheckDefault">
                                                    {{ __('Pre') }}
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input currency_note" type="radio"
                                                    name="site_currency_symbol_position" value="post"
                                                    @if (isset($settings['site_currency_symbol_position']) && $settings['site_currency_symbol_position'] == 'post') checked @endif
                                                    id="flexCheckChecked">
                                                <label class="form-check-label" for="flexCheckChecked">
                                                    {{ __('Post') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group mb-0 border border-1 rounded-1 p-2">
                                            <label class="col-form-label  p-0"
                                                for="new_note_value">{{ __('Preview :') }}</label>
                                            <span id="formatted_price_span"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-4 col-sm-6">
                            <div class="card">
                                <div class="card-header p-2">

                                    <label class="form-label col-form-label h6 mb-0 p-0"
                                        for="example3cols3Input">{{ __('Currency Symbol & Name') }}</label>
                                </div>
                                <div class="card-body p-2">
                                    <div class="form-group mb-0 switch-width">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input currency_note pointer" type="radio"
                                                name="site_currency_symbol_name" value="symbol"
                                                @if (!isset($settings['site_currency_symbol_name']) || $settings['site_currency_symbol_name'] == 'symbol') checked @endif id="currencySymbol">
                                            <label class="form-check-label" for="currencySymbol">
                                                {{ __('With Currency Symbol') }}
                                            </label>
                                        </div>
                                        <div class="form-check ">
                                            <input class="form-check-input currency_note pointer" type="radio"
                                                name="site_currency_symbol_name" value="symbolname"
                                                @if (isset($settings['site_currency_symbol_name']) && $settings['site_currency_symbol_name'] == 'symbolname') checked @endif
                                                id="currencySymbolName">
                                            <label class="form-check-label" for="currencySymbolName">
                                                {{ __('With Currency Name') }}
                                            </label>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            <div class="card-footer text-end p-3">
                <input class="btn btn-print-invoice  btn-primary " type="submit" value="{{ __('Save Changes') }}">
            </div>
            {{ Form::close() }}
        </div>
        </div>
    </div>
</div>
<!--week start settings-->
<div id="week-settings" class="card">
    <div class="card-header p-3">
        <h5>{{ __('Week Start At') }}</h5>
        <small
            class="text-muted">{{ __('Choose your preferred start day: Sunday or Monday - customize your calendar to suit your weekly rhythm.') }}</small>
    </div>
    <div class="bg-none">
        <div class="row company-setting">
            <div class="">
                {{ Form::open(['route' => ['company.week.setting.store'], 'id' => 'setting-week-form']) }}
                @csrf
                <div class="card-body p-3 pb-0">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="row row-gaps">
                                    <div class="col-xxl-4 col-sm-6">
                                        <div class="card mb-0">
                                            <div class="card-body p-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="week_start_day" value="0"
                                                        @if (!isset($settings['week_start_day']) || $settings['week_start_day'] == '0') checked @endif
                                                        id="flexCheckfirst">
                                                    <label class="form-check-label" for="flexCheckfirst">
                                                        {{ __('Sunday') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-4 col-sm-6">
                                        <div class="card mb-0">
                                            <div class="card-body p-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="week_start_day" value="1"
                                                        @if (isset($settings['week_start_day']) && $settings['week_start_day'] == '1') checked @endif
                                                        id="flexCheckoption">
                                                    <label class="form-check-label" for="flexCheckoption">
                                                        {{ __('Monday') }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end p-3">
                    <input class="btn btn-print-invoice  btn-primary " type="submit"
                        value="{{ __('Save Changes') }}">
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>

{{-- Booking Mode Setting --}}
<div class="card" id="booking-mode-sidenav">
    <div class="card-header p-3">
        <h5>{{ __('Booking Mode Settings') }}</h5>
        <small class="text-secondary font-weight-bold">
            {{ __('Choose a booking mode to manage new appointments efficiently.') }}
        </small>
    </div>
    {{ Form::open(['url' => route('company.booking.mode.store'), 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
    <div class="card-body p-3">
        <div class="row row-gaps">
            <!-- Login / Registration -->
            <div class="col-sm-6 col-12">
                <div class="form-group rounded-1 card list_colume_notifi p-3 h-100 mb-0">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2 p-0">
                        {{-- <div class="d-flex align-items-center justify-content-between border rounded p-3"> --}}
                            <label for="booking_mode_1" class="form-label mb-0">
                                <h6 class="mb-0">{{ __('Login / Registration') }}</h6>
                            </label>
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="booking_mode[1]" value="0" />
                                <input class="form-check-input" id="booking_mode_1" name="booking_mode[1]"
                                    type="checkbox" value="1"
                                    {{ empty($settings['booking_mode']) || in_array('1', explode(',', $settings['booking_mode'])) ? 'checked' : '' }}>
                            </div>
                        {{-- </div> --}}
                    </div>
                </div>
            </div>

            <!-- Guest -->
            <div class="col-sm-6 col-12">
                <div class="form-group rounded-1 card list_colume_notifi p-3 h-100 mb-0">
                    <div class="card-body d-flex align-items-center justify-content-between gap-2 p-0">
                        {{-- <div class="d-flex align-items-center justify-content-between border rounded p-3"> --}}
                            <label for="booking_mode_2" class="form-label mb-0">
                                <h6 class="mb-0">{{ __('Guest') }}</h6>
                            </label>
                            <div class="form-check form-switch">
                                <input type="hidden" name="booking_mode[2]" value="0" />
                                <input class="form-check-input" id="booking_mode_2" name="booking_mode[2]"
                                    type="checkbox" value="2"
                                    {{ isset($settings['booking_mode']) && in_array('2', explode(',', $settings['booking_mode'])) ? 'checked' : '' }}>
                            </div>
                        {{-- </div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="card-footer text-end">
        <input class="btn btn-print-invoice btn-primary" type="submit" value="{{ __('Save Changes') }}">
    </div>
    {{ Form::close() }}
</div>

<!--embedded code settings-->
<div id="embedded-code-sidenav" class="card">
    <div class="card-header p-3">
        <h5>{{ __('Embedded Code') }}</h5>
        <small class="text-muted">{{ __('Copy this code and put anywhere') }}</small>
    </div>
    <div class="bg-none">
        <div class="row company-setting">
            <div class="">
                <form id="setting-form" method="post" action="#" enctype ="multipart/form-data">
                    @csrf
                    <div class="card-body p-3 pb-0">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    {{ Form::label('embedded_code', __('Embedded Code'), ['class' => 'form-label']) }}
                                    {{ Form::textarea('embedded_code', EmbeddedCode(), ['class' => 'form-control', 'rows' => '2', 'readonly']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Custom JS --}}
<div class="card" id="custom-js-sidenav">
    <div class="card-header p-3">
        <h5>{{ 'Custom Js' }}</h5>
        <small class="text-secondary font-weight-bold">
        </small>
    </div>
    {{ Form::open(['url' => route('company.custom.js.store'), 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
    <div class="card-body p-3 pb-0">
        <div class="row">
            <div class="col-12 form-group">
                <div class="input-group">
                    {{ Form::textarea('custom_js', !empty($settings['custom_js']) ? $settings['custom_js'] : null, ['class' => 'form-control ', 'required' => 'required', 'placeholder' => 'console.log(hello);', 'rows' => 4]) }}
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer p-3 text-end">
        <input class="btn btn-print-invoice btn-primary" type="submit" value="{{ __('Save Changes') }}">
    </div>
    {{ Form::close() }}
</div>

{{-- Custom CSS --}}
<div class="card" id="custom-css-sidenav">
    <div class="card-header p-3">
        <h5>{{ 'Custom CSS' }}</h5>
        <small class="text-secondary font-weight-bold">
        </small>
    </div>
    {{ Form::open(['url' => route('company.custom.css.store'), 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
    <div class="card-body p-3 pb-0">
        <div class="row">
            <div class="col-12 form-group">
                <div class="input-group">
                    {{ Form::textarea('custom_css', !empty($settings['custom_css']) ? $settings['custom_css'] : null, ['class' => 'form-control ', 'required' => 'required', 'placeholder' => '<style>.body{color:aliceblue;}</style>', 'rows' => 4]) }}
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer p-3 text-end">
        <input class="btn btn-print-invoice btn-primary m-r-10" type="submit" value="{{ __('Save Changes') }}">
    </div>
    {{ Form::close() }}
</div>

{{-- Default Appointment Status --}}
<div class="card" id="default-appointment-status-sidenav">
    <div class="card-header p-3">
        <h5>{{ 'Default Appointment Status' }}</h5>
        <small class="text-secondary font-weight-bold">
            {{ __('Choose a default status to set how new appointments are automatically marked.') }}
        </small>
    </div>
    {{ Form::open(['url' => route('company.default.status.store'), 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
    @php
        use App\Models\CustomStatus;
        use Illuminate\Support\Collection;
        $customStatus = CustomStatus::where('created_by', creatorId())
            ->where('business_id', getActiveBusiness())
            ->get();
        $pendingStatus = [
            'id' => 'Pending',
            'title' => 'Pending',
            'created_by' => creatorId(),
            'business_id' => getActiveBusiness(),
        ];
        $customStatus = new Collection(array_merge([$pendingStatus], $customStatus->toArray()));
    @endphp
    <div class="card-body review-body p-3 pb-0">
        <div class="row">
            <div class="form-group col-md-12">
                <label class="form-label">{{ __('Appointment Status') }}</label>
                <div class="d-flex flex-wrap appointment-item-wrapper">
                    @foreach ($customStatus as $status)
                        <div class="form-check col-md-3 col-sm-3 col-6">
                            <input class="form-check-input cursor-pointer currency_note" type="radio" name="default_status"
                                value="{{ $status['id'] }}" @if (isset($settings['default_status']) && $settings['default_status'] == $status['id']) checked @endif
                                id="default_status_{{ $status['title'] }}">
                            <label class="form-check-label cursor-pointer" for="default_status_{{ $status['title'] }}">
                                {{ $status['title'] }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer p-3 text-end">
        <input class="btn btn-print-invoice btn-primary" type="submit" value="{{ __('Save Changes') }}">
    </div>
    {{ Form::close() }}
</div>



<script>
    if ($('#useradd-sidenav').length > 0) {
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '#useradd-sidenav',
            offset: 300,
        });
    }
    $('.colorPicker').on('click', function(e) {
        $('body').removeClass('custom-color');
        if (/^theme-\d+$/) {
            $('body').removeClassRegex(/^theme-\d+$/);
        }
        $('body').addClass('custom-color');
        $('.themes-color-change').removeClass('active_color');
        $(this).addClass('active_color');
        const input = document.getElementById("color-picker");
        setColor();
        input.addEventListener("input", setColor);

        function setColor() {
            $(':root').css('--color-customColor', input.value);
        }

        $(`input[name='color_flag`).val('true');
    });

    $('.themes-color-change').on('click', function() {

        $(`input[name='color_flag`).val('false');

        var color_val = $(this).data('value');
        $('body').removeClass('custom-color');
        if (/^theme-\d+$/) {
            $('body').removeClassRegex(/^theme-\d+$/);
        }
        $('body').addClass(color_val);
        $('.theme-color').prop('checked', false);
        $('.themes-color-change').removeClass('active_color');
        $('.colorPicker').removeClass('active_color');
        $(this).addClass('active_color');
        $(`input[value=${color_val}]`).prop('checked', true);
    });

    $.fn.removeClassRegex = function(regex) {
        return $(this).removeClass(function(index, classes) {
            return classes.split(/\s+/).filter(function(c) {
                return regex.test(c);
            }).join(' ');
        });
    };
</script>

<script>
    $(document).ready(function() {
        sendData();
        $('.currency_note').on('change', function() {
            sendData();
        });

        function sendData(selectedValue, type) {
            var formData = $('#setting-currency-form').serialize();
            $.ajax({
                type: 'POST',
                url: '{{ route('company.update.note.value') }}',
                data: formData,
                success: function(response) {
                    var formattedPrice = response.formatted_price;
                    $('#formatted_price_span').text(formattedPrice);
                }
            });
        }
    });
</script>
{{-- Dark Mod --}}
<script>
    var custdarklayout = document.querySelector("#cust-darklayout");
    custdarklayout.addEventListener("click", function() {
        if (custdarklayout.checked) {
            document.querySelector(".m-header > .b-brand > .logo-lg").setAttribute("src",
                "{{ $logo_light }}");
            document.querySelector("#main-style-link").setAttribute("href",
                "{{ asset('assets/css/style-dark.css') }}");
        } else {
            document.querySelector(".m-header > .b-brand > .logo-lg").setAttribute("src",
                "{{ $logo_dark }}");
            document.querySelector("#main-style-link").setAttribute("href",
                "{{ asset('assets/css/style.css') }}");
        }
    });

    function removeClassByPrefix(node, prefix) {
        for (let i = 0; i < node.classList.length; i++) {
            let value = node.classList[i];
            if (value.startsWith(prefix)) {
                node.classList.remove(value);
            }
        }
    }
</script>

{{-- @endpush --}}


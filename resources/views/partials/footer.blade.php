<footer class="dash-footer">
    <div class="footer-wrapper">
        <div class="py-1">
            <span class="text-muted">
                @php
                    $footerContent = $company_settings['footer_text'] ?? $admin_settings['footer_text'] ?? null;
                @endphp
                @if (!empty($footerContent))
                    {{ $footerContent }} {{ date('Y') }}@if (!str_contains($footerContent, 'PolytronX')) <span class="mx-1">|</span> Powered By PolytronX - Business Digitalized @endif
                @else
                    {{ __('Copyright') }} &copy; {{ config('app.name', 'ADC - Amad Diagnostic Centre') }} {{ date('Y') }} <span class="mx-1">|</span> Powered By PolytronX - Business Digitalized
                @endif
            </span>
        </div>
    </div>
</footer>

@if (Route::currentRouteName() !== 'chatify')
    <x-feedback.modal />
@endif
<x-feedback.loader />
<x-feedback.toast />

<!-- Required vendor JS -->
<script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
<script src="{{ asset('js/jquery.form.js') }}"></script>
<script src="{{ asset('assets/js/dash.js') }}"></script>
<script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/bootstrap.js') }}"></script>
<script src="{{ asset('assets/js/plugins/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/simple-datatables.js') }}"></script>
<script src="{{ asset('assets/js/plugins/bootstrap-switch-button.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/flatpickr.min.js') }}"></script>
<script src="{{ asset('assets/js/plugins/choices.js') }}"></script>
<script src="{{ asset('assets/js/repeater.js') }}"></script>
<script src="{{ asset('assets/js/plugins/datepicker-full.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap-datepicker.js') }}"></script>
<script src="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.js') }}"></script>
<script src="{{ asset('js/fontawesome-iconpicker.js') }}"></script>
<script src="{{ asset('js/icons.js') }}"></script>
<script src="{{ asset('js/socialSharing.js') }}"></script>
<script src="{{ asset('js/custom.js') }}"></script>

@if ($message = Session::get('success'))
    <script>
        toastrs('Success', {{ json_encode($message) }}, 'success');
    </script>
@endif

@if ($message = Session::get('error'))
    <script>
        toastrs('Error', {{ json_encode($message) }}, 'error');
    </script>
@endif

@stack('scripts')

@if (isset($admin_settings['enable_cookie']) && $admin_settings['enable_cookie'] == 'on')
    @include('layouts.cookie_consent')
@endif

{{-- custom-js --}}
<script type="text/javascript">
    {!! isset($admin_settings['custom_js']) ? htmlspecialchars_decode($admin_settings['custom_js']) : '' !!}
</script>
</body>

</html>

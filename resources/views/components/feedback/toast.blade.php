@props(['id' => 'liveToast'])
{{-- Unified toast — id liveToast (used in 3 layouts) with Bootstrap 5 markup + Alpine fallback --}}
<div class="position-fixed top-0 end-0 p-3" style="z-index: 99999">
    <div id="{{ $id }}" class="toast text-white text-bg-primary fade" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
        </div>
    </div>
</div>
{{-- Compat: JS calls toastrs() and bootstrap.Toast.getOrCreateInstance --}}

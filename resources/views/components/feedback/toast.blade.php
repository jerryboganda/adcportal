@props(['id' => 'liveToast'])
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: var(--adc-z-toast, 1090)">
    <div id="{{ $id }}" class="toast fade" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
        </div>
    </div>
</div>

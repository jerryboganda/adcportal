/* ADC UI helpers — confirm dialogs + toast bridge (vanilla, no deps) */

const CONFIRM_ATTR = 'data-adc-confirm';

function adcFireConfirm(el) {
    const form = el.closest('form');
    const message = el.getAttribute(CONFIRM_ATTR + '-message') || 'Are you sure you want to proceed?';
    const confirmText = el.getAttribute(CONFIRM_ATTR + '-text') || 'Yes, continue';
    const cancelText = el.getAttribute(CONFIRM_ATTR + '-cancel') || 'Cancel';

    if (window.Swal && typeof window.Swal.fire === 'function') {
        window.Swal.fire({
            title: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0caf60',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            focusCancel: true,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed && form) {
                form.requestSubmit();
            }
        });
    } else if (window.confirm(message)) {
        if (form) {
            form.requestSubmit();
        }
    }
}

document.addEventListener('click', (e) => {
    const el = e.target.closest('[' + CONFIRM_ATTR + ']');
    if (!el) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    adcFireConfirm(el);
}, true);

window.adcToast = function (type, message) {
    if (typeof window.toastrs === 'function') {
        const level = type === 'success' ? 'Success' : (type === 'error' || type === 'danger') ? 'Error' : 'Info';
        const kind = type === 'danger' ? 'error' : type;
        toastrs(level, message, kind);
    } else if (console && console.info) {
        console.info('[toast:' + type + '] ' + message);
    }
};

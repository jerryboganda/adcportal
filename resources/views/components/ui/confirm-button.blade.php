@props([
    'action',
    'method' => 'POST',
    'message' => __('Are you sure you want to proceed?'),
    'confirmText' => __('Yes, continue'),
    'cancelText' => __('Cancel'),
    'icon' => null,
    'formClass' => 'd-inline',
])

<form action="{{ $action }}" method="POST" class="{{ $formClass }}" {{ $attributes->except(['method', 'message', 'confirmText', 'cancelText', 'icon', 'formClass']) }}>
    @csrf
    @if (in_array(strtoupper($method), ['PUT', 'PATCH', 'DELETE']))
        @method(strtoupper($method))
    @endif
    <button type="submit"
        class="{{ $attributes->get('class', 'btn btn-outline-danger btn-sm') }}"
        data-adc-confirm
        data-adc-confirm-message="{{ $message }}"
        data-adc-confirm-text="{{ $confirmText }}"
        data-adc-confirm-cancel="{{ $cancelText }}">
        @if ($icon)<i class="ti ti-{{ $icon }}" aria-hidden="true"></i>@endif
        @isset($slot)<span>{{ $slot }}</span>@endisset
    </button>
</form>

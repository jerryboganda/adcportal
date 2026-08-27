@props(['name', 'label' => null, 'size' => null, 'class' => null])

@php
$sizeClass = match($size) {
    'sm' => 'ti-sm',
    'lg' => 'ti-lg',
    'xl' => 'ti-xl',
    default => null,
};
$classes = trim('ti ti-' . $name . ' ' . ($sizeClass ?? '') . ' ' . ($class ?? ''));
@endphp

<i {{ $attributes->merge(['class' => $classes]) }}
   @if ($label) role="img" aria-label="{{ $label }}"
   @else aria-hidden="true" @endif></i>

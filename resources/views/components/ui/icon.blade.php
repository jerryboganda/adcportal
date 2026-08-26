@props(['name', 'label' => null])

<i {{ $attributes->merge(['class' => 'ti ti-' . $name]) }} @if ($label) aria-label="{{ $label }}" @else aria-hidden="true" @endif></i>

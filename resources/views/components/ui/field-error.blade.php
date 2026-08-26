@props(['field'])

@error($field)
    <span {{ $attributes->merge(['class' => 'invalid-feedback d-block']) }} role="alert">
        <small>{{ $message }}</small>
    </span>
@enderror

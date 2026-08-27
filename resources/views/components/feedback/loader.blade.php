@props(['id' => 'loader'])
<div id="{{ $id }}" class="loader-wrapper d-none" aria-live="polite" aria-busy="true">
    <span class="site-loader" aria-hidden="true"></span>
    <h4 class="loader-content">{{ __('Loading . . .') }}</h4>
</div>

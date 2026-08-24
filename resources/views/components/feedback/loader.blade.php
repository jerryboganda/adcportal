@props(['id' => 'loader'])
{{-- Unified loader — supports both legacy selectors: #loader + .loader-wrapper/.loader-wrappers + .site-loader/.site-loaders --}}
<div id="{{ $id }}" class="loader-wrapper loader-wrappers d-none" style="display: none;" aria-live="polite" aria-busy="true">
    <span class="site-loader site-loaders" aria-hidden="true"></span>
    <h4 class="loader-content">{{ __('Loading . . .') }}</h4>
</div>
{{-- Legacy jQuery hooks: $('#loader').fadeIn() and $('.loader-wrapper').removeClass('d-none') both work --}}

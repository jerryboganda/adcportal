@props(['icon' => 'inbox', 'title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'text-center py-5']) }} role="status">
    <div class="mb-3">
        <i class="ti ti-{{ $icon }} fs-1 text-muted" aria-hidden="true" style="opacity:.55"></i>
    </div>
    @if ($title)
        <h6 class="mb-1">{{ $title }}</h6>
    @endif
    @if ($description)
        <p class="text-muted mb-3">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="d-flex flex-wrap gap-2 justify-content-center">{{ $actions }}</div>
    @endisset
</div>

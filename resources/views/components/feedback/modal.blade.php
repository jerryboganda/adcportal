@props(['id' => 'commonModal', 'title' => '', 'size' => null])
@php
    $sizeClass = match($size) {
        'sm'  => 'modal-sm',
        'lg'  => 'modal-lg',
        'xl'  => 'modal-xl',
        default => null,
    };
@endphp
<div id="{{ $id }}" class="modal fade" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog">
    <div class="modal-dialog {{ $sizeClass }}" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body body">
                {{ $slot ?? '' }}
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="commonModalOver" tabindex="-1" role="dialog" aria-labelledby="commonModalOverLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commonModalOverLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body body"></div>
        </div>
    </div>
</div>

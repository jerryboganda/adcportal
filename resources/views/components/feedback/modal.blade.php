@props(['id' => 'commonModal', 'title' => ''])
{{-- Unified modal — Bootstrap 5, focus trap via bootstrap.Modal API (replaces 200-line fireModal) --}}
<div id="{{ $id }}" class="modal fade" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="body modal-body">
                {{ $slot ?? '' }}
            </div>
        </div>
    </div>
</div>
{{-- Secondary overflow modal --}}
<div class="modal fade" id="commonModalOver" tabindex="-1" role="dialog" aria-labelledby="commonModalOverLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commonModalOverLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="body modal-body"></div>
        </div>
    </div>
</div>

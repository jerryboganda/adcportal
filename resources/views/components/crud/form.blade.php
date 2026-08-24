@props(['action', 'method' => 'POST', 'multipart' => false])
{{-- Generic CRUD form — replaces 16× create + 17× edit duplication --}}
<form action="{{ $action }}" method="{{ $method === 'GET' ? 'GET' : 'POST' }}" {{ $multipart ? 'enctype=multipart/form-data' : '' }} class="needs-validation" novalidate>
    @csrf
    @if(!in_array($method, ['GET','POST']))
        @method($method)
    @endif
    <div class="modal-body">
        {{ $slot }}
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
    </div>
</form>

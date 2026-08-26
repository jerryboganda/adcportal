

{{Form::model($permission, array('route' => array('permissions.update', $permission->id), 'method' => 'PUT')) }}

    <div class="form-group">
        {{Form::label('name',__('Name'),array('class'=>'col-form-label'))}}
        {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter Permission Name')))}}
        @error('name')
        <span class="invalid-name" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </span>
        @enderror
    </div>
    <div class="form-group">
        {{Form::label('module',__('Module'),array('class'=>'col-form-label'))}}
        <select class="form-control" data-trigger name="module" id="choices-single-default">
            @foreach ($modules as $module)
                <option value="{{ $module }}" {{ $permission->module == $module ? 'selected' : '' }}>{{ $module }}</option>
            @endforeach
        </select>
        @error('module')
        <span class="invalid-module" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </span>
        @enderror
    </div>

    <div class="text-end">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Update'),array('class'=>'btn  btn-primary'))}}
    </div>
{{Form::close()}}
@push('scripts')
<script>
    "use strict";
 var multipleCancelButton = new Choices(
        '#choices-single-default', {
            removeItemButton: true,
        }
        );

</script>
@endpush

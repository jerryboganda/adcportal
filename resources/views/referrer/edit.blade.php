{{ Form::model($referrer, ['route' => ['referrer.update', $referrer->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('name', __('Doctor Name'), ['class' => 'form-label']) }}
                <span class="text-danger">*</span>
                {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Enter doctor name'), 'required' => 'required']) }}
                @error('name')
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('specialty', __('Specialty'), ['class' => 'form-label']) }}
                {{ Form::text('specialty', null, ['class' => 'form-control', 'placeholder' => __('e.g., Cardiologist, General Physician')]) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('clinic', __('Clinic/Hospital'), ['class' => 'form-label']) }}
                {{ Form::text('clinic', null, ['class' => 'form-control', 'placeholder' => __('Enter clinic or hospital name')]) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('phone', __('Phone'), ['class' => 'form-label']) }}
                {{ Form::text('phone', null, ['class' => 'form-control', 'placeholder' => __('Enter phone number')]) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
                {{ Form::email('email', null, ['class' => 'form-control', 'placeholder' => __('Enter email address')]) }}
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active" {{ $referrer->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{ Form::submit(__('Update'), ['class' => 'btn btn-primary']) }}
</div>
{{ Form::close() }}

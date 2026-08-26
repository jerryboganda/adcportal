{{ Form::open(['url' => 'customer', 'method' => 'post', 'enctype' => 'multipart/form-data','class'=>'needs-validation','novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}
                {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Enter Customer Name'), 'required' => 'required']) }}
                @error('name')
                    <small class="invalid-name" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <div class="form-file">
                    {{Form::label('image',__('Image'),['class'=>'form-label']) }}
                    <input type="file" class="form-control mb-2" name="image" id="image" aria-label="file example" onchange="previewImage(this)">
                    <img src="" class="rounded overflow-hidden mt-2"  id="blah" width="15%" style="display: none;"/>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
                {{ Form::text('email', null, ['class' => 'form-control', 'placeholder' => __('Enter Customer Email'), 'required' => 'required']) }}
                @error('email')
                    <small class="invalid-email" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('password', __('Password'), ['class' => 'form-label']) }}
                <input type="password" name="password" id="password" class="form-control" placeholder="**********">
                @error('password')
                    <small class="invalid-password" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('mobile_no', __('Mobile No'), ['class' => 'form-label']) }}
                {{ Form::text('mobile_no', null, ['class' => 'form-control', 'placeholder' => __('Enter Customer Mobile No'),'required' => 'required']) }}
                <div class=" text-xs text-danger d-block mt-2">
                    {{ __('Please add mobile number with country code. (ex. +92)') }}
                </div>
                @error('mobile_no')
                    <small class="invalid-mobile" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('gender', __('Gender'), ['class' => 'form-label']) }}
                {!! Form::select('gender', ['male' => 'Male', 'female' => 'Female'], null, ['class' => 'form-control','required' => 'required']) !!}
                @error('gender')
                    <small class="invalid-gender" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('dob', __('Date of Birth'), ['class' => 'form-label']) }}
                {{ Form::date('dob', null, ['class' => 'form-control', 'placeholder' => __('Select Date'), 'required' => 'required']) }}
                @error('dob')
                    <small class="invalid-dob" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group mb-0">
                {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                {{ Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => __('Enter Description'), 'rows' => '4']) }}
                @error('description')
                    <small class="invalid-description" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
    </div>
</div>
<div class="modal-footer gap-3">
    <button type="button" class="btn m-0 btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{ Form::submit(__('Create'), ['class' => 'btn m-0 btn-primary']) }}
</div>
{{ Form::close() }}

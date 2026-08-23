@php
    // Single-clinic app: subscriber naming removed.
    $name =__('User');
@endphp
    {{Form::open(array('url'=>'users','method'=>'post','class'=>'needs-validation','novalidate'))}}
    <div class="modal-body">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('name',__('Name'),['class'=>'form-label']) }}
                    {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter '.($name).' Name'),'required'=>'required'))}}
                    @error('name')
                    <small class="invalid-name" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                    @enderror
                </div>
            </div>
            {{-- Single-clinic app: Business Name field removed. --}}
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('email',__('Email'),['class'=>'form-label'])}}
                    {{Form::email('email',null,array('class'=>'form-control','placeholder'=>__('Enter '.($name).' Email'),'required'=>'required'))}}
                    @error('email')
                    <small class="invalid-email" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                    @enderror
                </div>
            </div>
            {{-- Single-clinic app: roles selector always shown. --}}
                <div class="col-md-12">
                    <div class="form-group">
                        {{ Form::label('roles', __('Roles'),['class'=>'form-label']) }}
                        {{ Form::select('roles',$roles, null, ['class' => 'form-control', 'id' => 'user_id', 'data-toggle' => 'select','required'=>'required']) }}
                        <div class=" text-xs mt-1">
                            {{ __('Please create role here. ') }}
                            <a href="{{ route('roles.index') }}"><b>{{ __('Create role') }}</b></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('mobile_no',__('Mobile No'),['class'=>'form-label'])}}
                        {{Form::text('mobile_no',null,array('class'=>'form-control','placeholder'=>__('Enter User Mobile No'),'required'=>'required'))}}
                        <div class=" text-xs text-danger mt-1">
                            {{ __('Please add mobile number with country code. (ex. +92)') }}
                        </div>
                        @error('mobile_no')
                        <small class="invalid-mobile" role="alert">
                            <strong class="text-danger">{{ $message }}</strong>
                        </small>
                        @enderror
                    </div>
                </div>

            <div class="col-md-5 mb-3">
                <label for="password_switch">{{ __('Login is enable') }}</label>
                <div class="form-check form-switch custom-switch-v1 float-end">
                    <input type="checkbox" name="password_switch" class="form-check-input input-primary pointer" value="on" id="password_switch" {{ company_setting('password_switch')=='on'?' checked ':'' }} checked>
                    <label class="form-check-label" for="password_switch"></label>
                </div>
            </div>
            <div class="col-md-12 ps_div d-block">
                <div class="form-group mb-0">
                    {{Form::label('password',__('Password'),['class'=>'form-label'])}}
                    {{Form::password('password',array('class'=>'form-control','placeholder'=>__('Enter User Password'),'minlength'=>"6"))}}
                    @error('password')
                    <small class="invalid-password" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                    @enderror
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer gap-3">
        <button type="button" class="btn btn-secondary m-0" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Create'),array('class'=>'btn m-0 btn-primary'))}}
    </div>
    {{Form::close()}}

@php
    // Single-clinic app: subscriber naming removed.
    $name =__('User');
@endphp
    {{Form::model($user,array('route' => array('users.update', $user->id), 'method' => 'PUT','class'=>'needs-validation','novalidate')) }}
    <div class="modal-body pb-0">
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
                        {{ Form::select('roles',$roles, null, ['class' => 'form-control', 'id' => 'user_id', 'data-toggle' => 'select']) }}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {{Form::label('mobile_no',__('Mobile No'),['class'=>'form-label'])}}
                        {{Form::text('mobile_no',null,array('class'=>'form-control','placeholder'=>__('Enter User Mobile No'),'required'=>'required'))}}
                        <div class=" text-xs text-danger mt-2">
                            {{ __('Please add mobile number with country code. (ex. +92)') }}
                        </div>
                        @error('mobile_no')
                        <small class="invalid-mobile" role="alert">
                            <strong class="text-danger">{{ $message }}</strong>
                        </small>
                        @enderror
                    </div>
                </div>
        </div>
    </div>
    <div class="modal-footer gap-3">
        <button type="button" class="btn m-0 btn-secondary" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Update'),array('class'=>'btn m-0 btn-primary'))}}
    </div>
    {{Form::close()}}

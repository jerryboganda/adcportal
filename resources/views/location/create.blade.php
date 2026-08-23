{{ Form::open(['route' => ['location.store', ['business_id' => $business->id]], 'method' => 'post', 'enctype' => 'multipart/form-data','class'=>'needs-validation','novalidate']) }}
    <div class="modal-body">
        <div class="text-start mb-3">
            @if (false)
                @php
                    $admin_settings = getAdminAllSetting();
                @endphp
                @if (false && !empty($admin_settings['chatgpt_is']) && $admin_settings['chatgpt_is'] == 'on')
                    @include('aiassistant::ai.generate_ai_btn',['template_module' => 'location', 'module'=>'General'])
                @endif
            @endif
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('name',__('Location Name'),['class'=>'form-label']) }}
                    {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter Location Name'),'required'=>'required'))}}
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
                        <input type="file" class="form-control mb-2" name="location_image" id="location_image" aria-label="file example" onchange="previewImage(this)" required>
                        <img class="rounded overflow-hidden" src="" id="blah" width="15%" style="display: none; " class="mt-2"/>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('address',__('Address'),['class'=>'form-label']) }}
                    {{Form::text('address',null,array('class'=>'form-control','placeholder'=>__('Enter Address'),'required'=>'required','id'=>'address'))}}
                    @error('address')
                    <small class="invalid-address" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                    @enderror
                </div>
            </div>

            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('phone',__('Phone'),['class'=>'form-label']) }}
                    {{Form::text('phone',null,array('class'=>'form-control','placeholder'=>__('Enter Phone number'),'required'=>'required'))}}
                    @error('phone')
                    <small class="invalid-phone" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                    @enderror
                </div>
            </div>

            <div class="col-md-12">
                <div class="form-group mb-0">
                    {{Form::label('description',__('Description'),['class'=>'form-label']) }}
                    {{Form::textarea('description',null,array('class'=>'form-control','placeholder'=>__('Enter Description'),'required'=>'required','rows' => '4'))}}
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
        <button type="button" class="btn m-0 btn-secondary" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Create'),array('class'=>'btn m-0 btn-primary'))}}
    </div>
{{Form::close()}}


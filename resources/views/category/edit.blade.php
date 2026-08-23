{{Form::model($category,array('route' => array('category.update', $category->id), 'method' => 'PUT', 'id' => 'business-edit-form','enctype' => 'multipart/form-data','class'=>'needs-validation','novalidate')) }}
    <div class="modal-body">
        <div class="text-start mb-3">
            @if (false)
                @php
                    $admin_settings = getAdminAllSetting();
                @endphp
                @if (false && !empty($admin_settings['chatgpt_is']) && $admin_settings['chatgpt_is'] == 'on')
                    @include('aiassistant::ai.generate_ai_btn',['template_module' => 'category', 'module'=>'General'])
                @endif
            @endif
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group mb-0">
                    {{Form::label('name',__('Name'),['class'=>'form-label']) }}
                    {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter Category Name'),'required'=>'required'))}}
                    @error('name')
                    <small class="invalid-name" role="alert">
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

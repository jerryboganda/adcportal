
{{ Form::open(['url' => 'custom-status', 'method' => 'post', 'enctype' => 'multipart/form-data','class'=>'needs-validation','novalidate']) }}
<div class="modal-body">
    <div class="text-start mb-3">
        @if (false)
            @php
                $admin_settings = getAdminAllSetting();
            @endphp
            @if (false && !empty($admin_settings['chatgpt_is']) && $admin_settings['chatgpt_is'] == 'on')
                @include('aiassistant::ai.generate_ai_btn',['template_module' => 'custom status','module'=>'General'])
            @endif
        @endif
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
                {{ Form::text('title', null, ['class' => 'form-control', 'placeholder' => __('Enter Title'), 'required' => 'required']) }}
                @error('title')
                    <small class="invalid-title" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group mb-0">
                {{ Form::label('status_color', __('Status Color'), ['class' => 'form-label']) }}
                <input class="jscolor form-control" value="0B3110" name="status_color" id="color" required>
                @error('status_color')
                    <small class="invalid-status_color" role="alert">
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

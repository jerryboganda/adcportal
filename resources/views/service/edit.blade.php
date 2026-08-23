{{ Form::model($service, ['route' => ['service.update', $service->id], 'method' => 'PUT', 'id' => 'business-edit-form', 'enctype' => 'multipart/form-data', 'class' => 'needs-validation', 'novalidate']) }}
@php
    if (false) {
        $duration_class = 'col-md-4';
    } else {
        $duration_class = 'col-md-12';
    }
@endphp
<div class="modal-body">
    <div class="text-start mb-3">
        @if (false)
            @php
                $admin_settings = getAdminAllSetting();
            @endphp
            @if (false && !empty($admin_settings['chatgpt_is']) && $admin_settings['chatgpt_is'] == 'on')
                @include('aiassistant::ai.generate_ai_btn', [
                    'template_module' => 'service',
                    'module' => 'General',
                ])
            @endif
        @endif
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('name', __('Service Name'), ['class' => 'form-label']) }}
                {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('Enter Service Name'), 'required' => 'required']) }}
                @error('name')
                    <small class="invalid-name" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        @if (false && false)
            @stack('compound_and_collaborative_service_edit')
        @elseif (false)
            @stack('collaborative_service_edit')
        @elseif (false)
            @stack('compound_service_edit')
        @endif

        <div class="col-md-12">
            <div class="form-group">
                <div class="form-file">
                    {{Form::label('image',__('Image'),['class'=>'form-label']) }}
                    <input type="file" class="form-control mb-2" name="service_image" id="service_image" aria-label="file example" onchange="previewImage(this)">
                    <img class="rounded overflow-hidden" src="{{ check_file($service->image) ? get_file($service->image) : get_file('uploads/default/avatar.png') }}" id="blah" width="15%"/>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {{ Form::label('category', __('category'), ['class' => 'form-label']) }}
                {{ Form::select('category', $category, $service->category_id, ['class' => 'form-control', 'required' => 'required']) }}
                @permission('category create')
                    <div class=" text-xs mt-1">{{ __('Create category here. ') }}
                        <a href="#" data-ajax-popup="true" data-size="md"
                            data-title="{{ __('Create New Category') }}"
                            data-url="{{ route('category.create', ['business_id' => $service->business_id]) }}"
                            data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
                            <b>{{ __('Create category') }}</b>
                        </a>
                    </div>
                @endpermission
                @error('category')
                    <small class="invalid-category" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="col-md-6">
            @stack('service_tax')
        </div>

        <div class="col-md-12">
            @stack('discount')
        </div>

        <div class="col-md-12 mb-2">
            <div class="form-check form-switch custom-switch-v1">
                <label class="form-check-label" for="is_free">{{ __('Free Service') }}</label>
                <input type="checkbox" class="form-check-input input-primary" name="is_service_free"
                    id="is_service_free" {{ $service->is_free == 1 ? 'checked' : '' }}>
            </div>
        </div>

        <div class="col-md-12 d-none" id="price">
            <div class="form-group">
                {{ Form::label('price', __('Price'), ['class' => 'form-label']) }}
                {{ Form::number('price', null, ['class' => 'form-control', 'placeholder' => __('Enter Price')]) }}
                @error('price')
                    <small class="invalid-price" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="{{ $duration_class }}">
            <div class="form-group">
                {{ Form::label('duration', __('Duration (minute)'), ['class' => 'form-label']) }}
                {{ Form::number('duration', null, ['class' => 'form-control', 'placeholder' => __('Enter Duration'), 'required' => 'required', 'min' => '0', 'max' => '510']) }}
                @error('duration')
                    <small class="invalid-duration" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        @stack('unit_setup')

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                {{ Form::textarea('description', null, ['class' => 'form-control', 'placeholder' => __('Enter Description'), 'required' => 'required', 'rows' => '4']) }}
                @error('description')
                    <small class="invalid-description" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        @stack('edit_repeat_enable_disable')
    </div>
</div>
<div class="modal-footer gap-3">
    <button type="button" class="btn m-0 btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
    {{ Form::submit(__('Update'), ['class' => 'btn m-0 btn-primary']) }}
</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        if ($('#is_service_free').prop('checked')) {
            $('#price').addClass('d-none');
        } else {
            $('#price').removeClass('d-none');
        }
        $('#is_service_free').change(function() {
            if ($('#is_service_free').prop('checked')) {
                $('#price').addClass('d-none');
            } else {
                $('#price').removeClass('d-none');
            }
        });
    });
</script>

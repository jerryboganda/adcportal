{{ Form::open(['url' => 'appointment', 'method' => 'post', 'data-url' => route('appointment.duration'), 'id' => 'appointment-form-date','class'=>'needs-validation','novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('service', __('Service'), ['class' => 'form-label']) }}
                {{ Form::select('service', $service, null, ['class' => 'form-control service', 'required' => 'required', 'id' => 'service']) }}
                @permission('business update')
                    <div class=" text-xs mt-1">{{ __('Create service here. ') }}
                        <a href="{{ route('manage.business') }}"><b>{{ __('Create service') }}</b></a>
                    </div>
                @endpermission
                @error('service')
                    <small class="invalid-service" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>
        <div class="col-md-12 appoinment-customer-info">
            <div class="form-group">
                <div class="d-flex justify-content-between">
                    {{ Form::label('customer', __('Customer'), ['class' => 'form-label']) }}
                    <div class="form-check form-switch custom-control-inline">
                        <input type="checkbox" class="form-check-input" name="new_customer" id="new_customer">
                        <label class="form-check-label" for="new_customer">{{ __('New Customer?') }}</label>
                    </div>
                </div>
                <div id="customer_select_div">
                    {{ Form::select('customer', $customer, null, ['class' => 'form-control', 'id' => 'customer_id']) }}
                    @permission('customer manage')
                        <div class=" text-xs mt-1">{{ __('Create customer here. ') }}
                            <a href="{{ route('customer.index') }}"><b>{{ __('Create customer') }}</b></a>
                        </div>
                    @endpermission
                </div>
                @error('customer')
                    <small class="invalid-customer" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div id="new_customer_fields" style="display: none;">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {{ Form::label('customer_name', __('Name'), ['class' => 'form-label']) }}
                        {{ Form::text('customer_name', null, ['class' => 'form-control', 'placeholder' => __('Enter Name')]) }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {{ Form::label('customer_email', __('Email'), ['class' => 'form-label']) }}
                        {{ Form::email('customer_email', null, ['class' => 'form-control', 'placeholder' => __('Enter Email')]) }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {{ Form::label('customer_phone', __('Mobile Number'), ['class' => 'form-label']) }}
                        {{ Form::text('customer_phone', null, ['class' => 'form-control', 'placeholder' => __('Enter Mobile Number')]) }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {{ Form::label('customer_gender', __('Gender'), ['class' => 'form-label']) }}
                        <div class="d-flex radio-check">
                            <div class="form-check form-check-inline">
                                <input type="radio" id="g_male" value="Male" name="customer_gender" class="form-check-input" checked>
                                <label class="form-check-label" for="g_male">{{__('Male')}}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="radio" id="g_female" value="Female" name="customer_gender" class="form-check-input">
                                <label class="form-check-label" for="g_female">{{__('Female')}}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {{ Form::label('customer_dob', __('Date of Birth'), ['class' => 'form-label']) }}
                        {{ Form::date('customer_dob', null, ['class' => 'form-control']) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('location', __('Location'), ['class' => 'form-label']) }}
                {{ Form::select('location', $location, null, ['class' => 'form-control', 'required' => 'required']) }}
                @permission('business update')
                    <div class=" text-xs mt-1">{{ __('Create location here. ') }}
                        <a href="{{ route('manage.business') }}"><b>{{ __('Create location') }}</b></a>
                    </div>
                @endpermission
                @error('location')
                    <small class="invalid-location" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>


        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('staff', __('Staff'), ['class' => 'form-label']) }}
                {{ Form::select('staff', $staff, null, ['class' => 'form-control', 'id' => 'staff']) }}
                @permission('business update')
                    <div class=" text-xs mt-1">{{ __('Create staff here. ') }}
                        <a href="{{ route('manage.business') }}"><b>{{ __('Create staff') }}</b></a>
                    </div>
                @endpermission
                @error('staff')
                    <small class="invalid-staff" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
                {{ Form::textarea('notes', null, ['class' => 'form-control', 'placeholder' => __('Enter notes'), 'rows' => '4']) }}
                @error('notes')
                    <small class="invalid-notes" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                {{ Form::label('referred_by', __('Referred By'), ['class' => 'form-label']) }}
                {{ Form::text('referred_by', null, ['class' => 'form-control', 'placeholder' => __('Enter Referring Doctor Name')]) }}
                @error('referred_by')
                    <small class="invalid-feedback" role="alert">
                        <strong class="text-danger">{{ $message }}</strong>
                    </small>
                @enderror
            </div>
        </div>

        {!! Form::hidden('appointment_status', 'Pending') !!}
        <div class="form-group col-md-6">
            <label for="appointment_date" class="col-form-label pt-0">{{ __('Appointment Date') }}</label>
            <div class="input-group date ">
                <input class="form-control datepicker p-2 px-3" type="text" id="datepicker" name="appointment_date" placeholder="DD-MM-YYYY"
                    autocomplete="off" required="required" data-dates={{ json_encode($combinedArray) }}
                    data-holiday={{ json_encode($businesholiday) }}>
                <span class="input-group-text">
                    <i class="feather icon-calendar"></i>
                </span>
            </div>
        </div>
        <div id="timeSlotsContainer"></div>
        @stack('setting_setup')
    </div>
    <div class="modal-footer p-0 pt-3 gap-3">
        <button type="button" class="btn m-0  btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        {{ Form::submit(__('Create'), ['class' => 'btn m-0 btn-primary']) }}
    </div>
    {{ Form::close() }}
</div>
<script>
    $(document).on('change', '#new_customer', function() {
        if ($(this).is(':checked')) {
            $('#customer_select_div').hide();
            $('#new_customer_fields').show();
            $('#customer_id').removeAttr('required');
            $('#new_customer_fields input').attr('required', 'required');
        } else {
            $('#customer_select_div').show();
            $('#new_customer_fields').hide();
            $('#customer_id').attr('required', 'required');
            $('#new_customer_fields input').removeAttr('required');
        }
    });
</script>

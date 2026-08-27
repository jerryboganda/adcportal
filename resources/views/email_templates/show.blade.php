@extends('layouts.main')
@section('page-title')
    {{ __('Email Templates') }}
@endsection
@section('page-breadcrumb')
    {{ __('Email Templates') }}
@endsection
@push('css')
    <link href="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.css') }}" rel="stylesheet">
@endpush

@section('page-action')
    <div class="d-flex flex-wrap justify-content-lg-end drp-languages">
        <a href="{{ route('email-templates.index') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
            data-bs-placement="top" title="{{ __('Return') }}"><i class="ti ti-arrow-back-up" aria-hidden="true"></i>
        </a>
        <ul class="list-unstyled mb-0 m-2">
            <li class="dropdown dash-h-item drp-language country-select">
                <a class="dash-head-link dropdown-toggle arrow-none me-0 bg-info rounded-1" data-bs-toggle="dropdown"
                    href="#" role="button" aria-haspopup="false" aria-expanded="false" id="dropdownLanguage">
                    <span class="drp-text hide-mob">{{ Str::upper($currEmailTempLang->lang) }}</span>
                    <i class="ti ti-chevron-down drp-arrow email_arrow"></i>
                </a>
                <div class="dropdown-menu dash-h-dropdown dropdown-menu-end" aria-labelledby="dropdownLanguage">
                    @foreach ($languages as $key => $lang)
                        <a href="{{ route('manage.email.language', [$emailTemplate->id, $key]) }}"
                            class="dropdown-item {{ $currEmailTempLang->lang == $key ? 'active' : '' }}">{{ Str::ucfirst($lang) }}</a>
                    @endforeach
                </div>
            </li>
        </ul>
    </div>
@endsection



@section('content')
    <div class="row">
        <div class="col-md-4 col-12">
            <div class="card w-100">
                <div class="card-header card-body w-100">
                    <h5></h5>
                    {{ Form::model($emailTemplate, ['route' => ['email_template.update', $emailTemplate->id], 'method' => 'PUT']) }}
                    <div class="row">
                        <div class="form-group col-xxl-6 col-12">
                            {{ Form::label('name', __('Name'), ['class' => 'col-form-label text-dark pt-0']) }}
                            {{ Form::text('name', null, ['class' => 'form-control font-style', 'disabled' => 'disabled']) }}
                        </div>
                        <div class="form-group col-xxl-6 col-12">
                            {{ Form::label('from', __('From'), ['class' => 'col-form-label text-dark pt-0']) }}
                            {{ Form::text('from', null, ['class' => 'form-control font-style', 'required' => 'required']) }}
                        </div>
                        {{ Form::hidden('lang', $currEmailTempLang->lang, ['class' => '']) }}
                        <div class="col-12 text-end">
                            <input type="submit" value="{{ __('Save') }}"
                                class="btn btn-print-invoice  btn-primary">
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
        <div class="col-md-8 col-12 d-flex">
            <div class="card w-100">
                <div class="card-header card-body w-100">
                    <h5></h5>
                    <div class="row text-xs">

                        <h6 class="font-weight-bold mb-4">{{ __('Variables') }}</h6>
                        @php
                            $variables = json_decode($currEmailTempLang->variables);
                        @endphp
                        @if (!empty($variables) > 0)
                            @foreach ($variables as $key => $var)
                            <div class="col-sm-6 pb-1 mb-sm-0 mb-2 ">
                                    <p class="mb-1">{{ __($key) }} : <span
                                            class="pull-right text-primary">{{ '{' . $var . '}' }}</span></p>
                                </div>
                            @endforeach
                        @endif



                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <h5></h5>

            <div class="card w-100">
                <div class="card-body">
                    {{ Form::model($currEmailTempLang, ['route' => ['store.email.language', $currEmailTempLang->parent_id], 'method' => 'PUT']) }}
                    <div class="row">
                        <div class="form-group col-12">
                            {{ Form::label('subject', __('Subject'), ['class' => 'col-form-label text-dark']) }}
                            {{ Form::text('subject', null, ['class' => 'form-control font-style', 'required' => 'required']) }}
                        </div>
                        <div class="form-group col-12">
                            {{ Form::label('content', __('Email Message'), ['class' => 'col-form-label text-dark']) }}
                            {{ Form::textarea('content', $currEmailTempLang->content, ['class' => 'summernote', 'id' => 'content', 'required' => 'required']) }}
                        </div>

                        <div class="col-md-12 text-end mb-0">
                            {{ Form::hidden('lang', null) }}
                            <input type="submit" value="{{ __('Save') }}"
                                class="btn btn-print-invoice  btn-primary">
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('assets/js/plugins/summernote-0.8.18-dist/summernote-lite.min.js') }}"></script>
@endpush

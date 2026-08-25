@extends('layouts.main')

@section('page-title')
    {{__('Theme Customize')}}
@endsection

@section('page-breadcrumb')
    {{ __('Theme Customize') }},
    {{ Module_Alias_Name($id) }}
@endsection

@section('page-action')
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/brands.min.css') }}">
@endpush


@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <h2 class="section-title mb-3">{{ Module_Alias_Name($id) }}</h2>
                        <p class="section-lead">
                            {{ __('Organize and adjust all settings about') }} {{ Module_Alias_Name($id) }}.
                        </p>
                    </div>
                </div>

                <div class="row">
                    @foreach ($theme_json as $json_settings)
                        <div class="col-lg-4 col-md-6 col-12 large-card">
                            <div class="card">
                                <div class="card-body p-3">
                                    <div class="card-icon text-white mb-3">
                                        <div class="bg-primary d-inline-block p-3 rounded-2">
                                            <i class="{{ $json_settings['icon'] }}"></i>
                                        </div>
                                    </div>
                                    <h4 class="mb-2">{{ $json_settings['title'] }}</h4>
                                    <p class="mb-2">{{ $json_settings['detail'] }}</p>
                                    @permission('theme edit')
                                        <div>
                                            <a href="{{ route('customize.edit', [$id, $json_settings['slug'], $json_settings['sections'][0]['slug'], $businessID ]) }}"
                                                class="card-btn text-primary d-flex align-items-center gap-2">{{ __('Change Setting') }} <i class="fas fa-chevron-right"></i></a>
                                        </div>
                                    @endpermission
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@endpush

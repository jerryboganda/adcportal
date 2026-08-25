@extends('layouts.main')

@section('page-title')
    {{ __('Report Templates') }}
@endsection

@section('page-breadcrumb')
    {{ __('Masters') }}, {{ __('Report Templates') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h5>{{ __('Create Template') }}</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('report-templates.store') }}">
                        @csrf
                        <div class="mb-2"><label class="form-label">{{ __('Template Name') }} *</label>
                            <input name="name" class="form-control" required placeholder="{{ __('e.g. Chest X-Ray Normal') }}"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">{{ __('Procedure (optional)') }}</label>
                                <select name="service_id" class="form-select">
                                    <option value="">{{ __('— any —') }}</option>
                                    @foreach($services as $sid => $sname)
                                        <option value="{{ $sid }}">{{ $sname }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Modality (optional)') }}</label>
                                <select name="modality_id" class="form-select">
                                    <option value="">{{ __('— any —') }}</option>
                                    @foreach($modalities as $mid => $mname)
                                        <option value="{{ $mid }}">{{ $mname }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-2"><label class="form-label">{{ __('Technique boilerplate') }}</label>
                            <textarea name="technique" rows="2" class="form-control" placeholder="{{ __('e.g. PA and lateral chest radiographs were obtained…') }}"></textarea></div>
                        <div class="mb-2"><label class="form-label">{{ __('Findings skeleton') }}</label>
                            <textarea name="findings" rows="3" class="form-control"></textarea></div>
                        <div class="mb-2"><label class="form-label">{{ __('Impression skeleton') }}</label>
                            <textarea name="impression" rows="2" class="form-control"></textarea></div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1">
                            <label class="form-check-label" for="is_default">{{ __('Use as global default template') }}</label>
                        </div>
                        @permission('report template create')
                        <button class="btn btn-primary btn-sm">{{ __('Create Template') }}</button>
                        @endpermission
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h5>{{ __('Configured Templates') }}</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>{{__('Name')}}</th><th>{{__('Procedure')}}</th><th>{{__('Default')}}</th><th class="text-end">{{__('Action')}}</th></tr></thead>
                        <tbody>
                        @forelse($templates as $tpl)
                            <tr>
                                <td class="fw-bold">{{ $tpl->name }}</td>
                                <td>{{ optional($tpl->serviceData)->name ?? __('Any procedure') }}</td>
                                <td>@if($tpl->is_default)<span class="badge bg-success">{{ __('Yes') }}</span>@endif</td>
                                <td class="text-end">
                                    @permission('report template delete')
                                    <form method="POST" action="{{ route('report-templates.destroy', $tpl->id) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger show_confirm"><i class="ti ti-trash"></i></button>
                                    </form>
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No templates yet — reports will start blank.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

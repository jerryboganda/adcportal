@extends('layouts.main')

@section('page-title')
    {{ __('Safety Screening Forms') }}
@endsection

@section('page-breadcrumb')
    {{ __('Masters') }}, {{ __('Safety Screening') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5>{{ __('Create Screening Form') }}</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('screening-forms.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('Form Name') }} *</label>
                            <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g. MRI Safety Screening') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Attach to Modality') }}</label>
                            <select name="modality_id" class="form-select">
                                <option value="">{{ __('All modalities (general)') }}</option>
                                @foreach(\App\Models\Modality::forClinic()->get() as $mod)
                                    <option value="{{ $mod->id }}">{{ $mod->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Questions') }} *</label>
                            <small class="d-block text-muted mb-2">{{ __('One per block. Risk Value = the answer that flags danger (e.g. yes). Select options separated by new lines.') }}</small>
                            @for($i = 0; $i < 4; $i++)
                                <div class="border rounded p-2 mb-2 bg-light">
                                    <input type="text" name="questions[{{ $i }}][question_text]" class="form-control form-control-sm mb-1"
                                        placeholder="{{ __('Question text') }}">
                                    <div class="row g-1">
                                        <div class="col-4">
                                            <select name="questions[{{ $i }}][answer_type]" class="form-select form-select-sm">
                                                <option value="boolean">{{ __('Yes / No') }}</option>
                                                <option value="select">{{ __('Select') }}</option>
                                                <option value="text">{{ __('Text') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <input type="text" name="questions[{{ $i }}][risk_value]" class="form-control form-control-sm"
                                                placeholder="{{ __('Risk value') }}">
                                        </div>
                                        <div class="col-4 d-flex align-items-center small">
                                            <input type="hidden" name="questions[{{ $i }}][is_risk_blocking]" value="1">
                                            <span class="text-muted">{{ __('Blocking') }}</span>
                                        </div>
                                    </div>
                                    <textarea name="questions[{{ $i }}][options_text]" rows="1" class="form-control form-control-sm mt-1"
                                        placeholder="{{ __('Select options (one per line) — optional') }}"></textarea>
                                </div>
                            @endfor
                        </div>
                        @permission('report template create')
                        <button class="btn btn-primary btn-sm">{{ __('Create Form') }}</button>
                        @endpermission
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5>{{ __('Configured Forms') }}</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>{{__('Name')}}</th><th>{{__('Modality')}}</th><th>{{__('Questions')}}</th><th>{{__('Status')}}</th><th class="text-end">{{__('Action')}}</th></tr></thead>
                        <tbody>
                        @forelse($forms as $form)
                            <tr>
                                <td class="fw-bold">{{ $form->name }}</td>
                                <td>{{ optional($form->modality)->name ?? __('All') }}</td>
                                <td>{{ $form->questions_count }}</td>
                                <td>
                                    <span class="badge {{ $form->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $form->is_active ? __('Active') : __('Disabled') }}</span>
                                </td>
                                <td class="text-end">
                                    @permission('report template edit')
                                    <form method="POST" action="{{ route('screening.forms.toggle', $form->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">{{ $form->is_active ? __('Disable') : __('Enable') }}</button>
                                    </form>
                                    @endpermission
                                    @permission('report template delete')
                                    <form method="POST" action="{{ route('screening.forms.destroy', $form->id) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger show_confirm"><i class="ti ti-trash"></i></button>
                                    </form>
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No screening forms yet. Create one for MRI or contrast studies.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

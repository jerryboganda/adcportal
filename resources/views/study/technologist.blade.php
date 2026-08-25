@extends('layouts.main')

@section('page-title')
    {{ __('Technologist Worklist') }}
@endsection

@section('page-breadcrumb')
    {{ __('Radiology Workflow') }}, {{ __('Technologist Worklist') }}
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <form method="GET" action="{{ route('study.technologist') }}" class="d-flex gap-2">
            <select name="modality" class="form-select form-select-sm" style="min-width:180px">
                <option value="">{{ __('All Modalities') }}</option>
                @foreach($modalities as $mod)
                    <option value="{{ $mod->id }}" {{ request('modality') == $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                @endforeach
            </select>
            <label class="d-flex align-items-center gap-1 small text-muted ms-2">
                <input type="checkbox" name="all" value="1" {{ request('all') ? 'checked' : '' }}> {{ __('All dates') }}
            </label>
            <button class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
        </form>
    </div>

    @forelse($studies as $modalityId => $group)
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0">{{ $group->first()?->ServiceData?->modality?->name ?? __('Unassigned Modality') }}
                    <span class="badge bg-primary ms-2">{{ $group->count() }}</span></h6>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr>
                            <th>{{ __('Time / Date') }}</th>
                            <th>{{ __('Patient') }}</th>
                            <th>{{ __('Procedure') }}</th>
                            <th>{{ __('Screening') }}</th>
                            <th>{{ __('State') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($group as $study)
                            @php
                                $state = $study->state();
                                $riskBlocked = $study->hasUnresolvedScreeningRisk();
                                $when = \Carbon\Carbon::createFromFormat('Y-m-d', (string) ($study->date_sort ?? now()->format('Y-m-d')))->format('d M')
                                    .' '.\Carbon\Carbon::createFromFormat('H:i', substr((string)$study->time, 0, 5))->format('H:i');
                            @endphp
                            <tr>
                                <td>{{ $when }}</td>
                                <td>{{ $study->patientDisplayName() }}<br>
                                    <small class="text-muted">{{ strtoupper($study->priority) }}</small></td>
                                <td>{{ optional($study->ServiceData)->name ?? '-' }}</td>
                                <td>
                                    @if(optional($study->ServiceData)->requires_screening || $study->screening_required)
                                        @if($riskBlocked)
                                            <span class="badge bg-danger">{{ __('Risk — blocked') }}</span>
                                        @elseif($study->screening_cleared)
                                            <span class="badge bg-success">{{ __('Cleared') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                        @endif
                                        @permission('study screen')
                                        <a href="{{ route('screening.answer', $study->id) }}" class="btn btn-sm btn-outline-secondary ms-1"><i class="ti ti-clipboard-check"></i></a>
                                        @endpermission
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $state->color() }}">{{ $state->label() }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end flex-wrap">
                                        @permission('appointment manage')
                                        @if(in_array($state, [\App\Enums\StudyState::CheckedIn, \App\Enums\StudyState::Preparing]))
                                            <form method="POST" action="{{ route('study.transition', $study->id) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="{{ $state === \App\Enums\StudyState::CheckedIn ? 'prepare' : 'start' }}">
                                                <button class="btn btn-sm btn-warning"
                                                    {{ $riskBlocked ? 'disabled title="'.__('Blocked: resolve screening risk').'"' : '' }}>
                                                    {{ $state === \App\Enums\StudyState::CheckedIn ? __('Prepare') : __('Start Scan') }}
                                                </button>
                                            </form>
                                        @elseif($state === \App\Enums\StudyState::InProgress)
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#complete-modal-{{ $study->id }}">{{ __('Complete + Dose') }}</button>
                                        @elseif($state === \App\Enums\StudyState::Acquired)
                                            <span class="badge bg-purple text-white align-self-center">{{ __('Sent to reading') }}</span>
                                            <form method="POST" action="{{ route('study.transition', $study->id) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="to_reading">
                                                <button class="btn btn-sm btn-outline-primary btn-sm">{{ __('Send to Reading') }}</button>
                                            </form>
                                        @endif
                                        @endpermission

                                        {{-- Complete + dose modal --}}
                                        @if($state === \App\Enums\StudyState::InProgress)
                                        <div class="modal fade" id="complete-modal-{{ $study->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('study.transition', $study->id) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="complete">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Complete Acquisition') }} — {{ $study->patientDisplayName() }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-2">
                                                                <div class="col-6">
                                                                    <label class="form-label">{{ __('Dose Value') }}</label>
                                                                    <input type="number" step="0.001" name="dose_value" class="form-control form-control-sm">
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label">{{ __('Unit') }}</label>
                                                                    <select name="dose_unit" class="form-select form-select-sm">
                                                                        <option value="">—</option>
                                                                        <option>mGy.cm</option><option>mGy</option><option>mSv</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label">{{ __('Contrast Agent') }}</label>
                                                                    <input type="text" name="contrast_agent" class="form-control form-control-sm">
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label">{{ __('Volume (ml)') }}</label>
                                                                    <input type="number" step="0.1" name="contrast_volume_ml" class="form-control form-control-sm">
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="form-label">{{ __('Technique Notes') }}</label>
                                                                    <textarea name="technique_notes" rows="2" class="form-control form-control-sm"></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                                            <button type="submit" class="btn btn-success btn-sm">{{ __('Mark Acquired') }}</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center py-5 text-muted">
            <i class="ti ti-device-desktop-off" style="font-size:48px"></i>
            <p class="mt-2 mb-0">{{ __('No active studies on the worklist.') }}</p>
        </div></div>
    @endforelse
@endsection

@extends('layouts.main')

@section('page-title')
    {{ isset($report) ? __('Edit Report') : __('Structured Report') }} — {{ $appointment->patientDisplayName() }}
@endsection

@section('page-breadcrumb')
    {{ __('Reading Worklist') }}, {{ __('Report Editor') }}
@endsection

@push('css')
<style>
    .snippet-btn { cursor: pointer; }
    #report-form textarea { font-family: ui-monospace, monospace; font-size: 0.92rem; }
</style>
@endpush

@section('content')
    <form id="report-form" method="POST"
          action="{{ isset($report) ? route('reports.update', $report->id) : route('reports.store', $appointment->id) }}">
        @csrf
        @if(isset($report)) @method('PUT') @endif

        <div class="row">
            {{-- Context sidebar --}}
            <div class="col-xl-3">
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0">{{ __('Study Context') }}</h6></div>
                    <div class="card-body small">
                        <p class="mb-1"><strong>{{ __('Patient') }}:</strong> {{ $appointment->patientDisplayName() }}</p>
                        <p class="mb-1"><strong>{{ __('MRN') }}:</strong> {{ optional($appointment->CustomerData)->mrn ?? '-' }}</p>
                        <p class="mb-1"><strong>{{ __('Procedure') }}:</strong> {{ optional($appointment->ServiceData)->name }}</p>
                        <p class="mb-1"><strong>{{ __('Date') }}:</strong> {{ $appointment->date }} {{ \Illuminate\Support\Str::substr($appointment->time, 0, 5) }}</p>
                        <p class="mb-1"><strong>{{ __('Referrer') }}:</strong> {{ optional($appointment->ReferrerData)->name ?? '-' }}</p>
                        <hr>
                        <strong class="d-block mb-1">{{ __('Clinical Notes (booking)') }}</strong>
                        <p class="text-muted mb-2">{{ $appointment->notes ?: '-' }}</p>

                        @if($appointment->screening_required)
                            <strong class="d-block mb-1">{{ __('Screening Answers') }}</strong>
                            <ul class="ps-3 mb-0">
                                @foreach($appointment->screeningAnswers as $ans)
                                    <li class="{{ $ans->is_risk ? 'text-danger fw-bold' : '' }}">
                                        {{ $ans->question?->question_text }}: {{ $ans->answer_value }}
                                        @if($ans->is_risk && $ans->override_reason)
                                            <em class="d-block small">({{ __('Overridden') }}: {{ $ans->override_reason }})</em>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                @if(!empty($previous) || $appointment->radiologyReports->count())
                    <div class="card">
                        <div class="card-header py-2"><h6 class="mb-0">{{ __('Prior Report Versions') }}</h6></div>
                        <div class="card-body small">
                            @foreach($appointment->radiologyReports as $r)
                                <p class="mb-1">
                                    v{{ $r->version }} · {{ ucfirst($r->type) }}
                                    {{ $r->isSigned() ? '✔' : '(draft)' }}
                                    — {{ optional($r->signed_at)?->format('d M H:i') }}
                                    @if($r->isSigned())
                                        <a href="{{ route('reports.pdf', $r->id) }}" target="_blank" rel="noopener"
                                            class="ms-1 small" aria-label="{{ __('Open version PDF') }}">{{ __('open PDF') }}</a>
                                    @endif
                                </p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($appointment->state() === \App\Enums\StudyState::Reading)
                    <div class="card">
                        <div class="card-header py-2"><h6 class="mb-0 text-danger">{{ __('Reject to Technologist') }}</h6></div>
                        <div class="card-body small">
                            <form method="POST" action="{{ route('study.transition', $appointment->id) }}"
                                onsubmit="return confirm('{{ __('Reject this study back to the technologist? A reason is required.') }}')">
                                @csrf
                                <input type="hidden" name="action" value="reject">
                                <label class="form-label" for="reject-reason">{{ __('Reason (required)') }}</label>
                                <textarea id="reject-reason" name="reason" rows="2" class="form-control form-control-sm" required></textarea>
                                <button type="submit" class="btn btn-sm btn-outline-danger mt-2">
                                    <i class="ti ti-arrow-back-up me-1"></i>{{ __('Send back for re-acquisition') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Editor --}}
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">{{ __('Radiology Report') }}
                            @if(isset($report))
                                <span class="badge bg-secondary ms-1">v{{ $report->version }} · {{ ucfirst($report->type) }}</span>
                            @endif
                        </h5>
                        @if(isset($template))
                            <small class="text-muted">{{ __('Template:') }} {{ $template->name }}</small>
                        @endif
                    </div>
                    <div class="card-body">
                        @if(isset($report) && $report->type === 'addendum')
                            <div class="alert alert-warning py-2">{{ __('This is an addendum to version :v. Document the reason and additional findings.', ['v' => optional($report->parentReport)->version ?? '-']) }}</div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Clinical History') }}</label>
                            <textarea name="clinical_history" rows="2" class="form-control">{{ old('clinical_history', $report->clinical_history ?? ($prefill['clinical_history'] ?? $appointment->notes)) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Technique') }}</label>
                            <textarea name="technique" rows="2" class="form-control">{{ old('technique', $report->technique ?? ($prefill['technique'] ?? '')) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Comparison') }}</label>
                            <input type="text" name="comparison" class="form-control" value="{{ old('comparison', $report->comparison ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Findings') }} *</label>
                            <textarea name="findings" rows="9" class="form-control" required>{{ old('findings', $report->findings ?? ($prefill['findings'] ?? '')) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Impression') }} *</label>
                            <textarea name="impression" rows="4" class="form-control" required>{{ old('impression', $report->impression ?? ($prefill['impression'] ?? '')) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">{{ __('Recommendations') }}</label>
                            <textarea name="recommendations" rows="2" class="form-control">{{ old('recommendations', $report->recommendations ?? ($prefill['recommendations'] ?? '')) }}</textarea>
                        </div>

                        <div class="border rounded p-3 bg-light">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="critical_flag" name="critical_flag" value="1"
                                    {{ old('critical_flag', isset($report) && $report->critical_flag) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-danger" for="critical_flag">
                                    {{ __('CRITICAL FINDING — requires immediate referrer notification') }}
                                </label>
                            </div>

                            @permission('report sign')
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="sign_now" name="sign_now" value="1">
                                <label class="form-check-label fw-bold" for="sign_now">{{ __('Sign immediately on save') }}</label>
                            </div>
                            <div class="row g-2 align-items-center" id="sign-options" style="display:none">
                                <div class="col-auto">
                                    <select name="sign_as" class="form-select form-select-sm">
                                        <option value="final">{{ __('Final report') }}</option>
                                        <option value="preliminary">{{ __('Preliminary report') }}</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <input type="text" name="signature_confirm" class="form-control form-control-sm"
                                        placeholder="{{ __('Type your full name to confirm') }}" style="min-width:240px">
                                </div>
                            </div>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('reports.worklist') }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Save Report') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const signNow = document.getElementById('sign_now');
        if (signNow) {
            signNow.addEventListener('change', function () {
                document.getElementById('sign-options').style.display = signNow.checked ? 'flex' : 'none';
            });
        }

        const form = document.getElementById('report-form');
        if (!form) return;
        let dirty = false;
        form.addEventListener('input', function () { dirty = true; }, { passive: true });
        form.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });

        const sigInput = form.querySelector('input[name="signature_confirm"]');
        if (sigInput) {
            sigInput.addEventListener('input', function () {
                sigInput.setCustomValidity('');
            }, { passive: true });
        }
    });
</script>
@endpush

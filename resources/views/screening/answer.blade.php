@extends('layouts.main')

@section('page-title')
    {{ __('Safety Screening') }} — {{ $appointment->patientDisplayName() }}
@endsection

@section('page-breadcrumb')
    {{ __('Radiology Workflow') }}, {{ __('Safety Screening') }}
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ $form->name }}</h5>
                    <small class="text-muted">
                        {{ __('Study #:id — :proc', ['id' => $appointment->id, 'proc' => optional($appointment->ServiceData)->name]) }}
                        @if($appointment->state() !== \App\Enums\StudyState::CheckedIn && $appointment->state() !== \App\Enums\StudyState::Preparing)
                            <span class="badge bg-warning text-dark ms-2">{{ __('Screening is normally completed before acquisition.') }}</span>
                        @endif
                    </small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('screening.submit', $appointment->id) }}">
                        @csrf
                        @foreach($questions as $q)
                            @php $saved = $existing->get($q->id); @endphp
                            <div class="border rounded p-3 mb-3 screening-item {{ ($saved?->is_risk && empty($saved?->override_reason)) ? 'border-danger' : '' }}"
                                data-risk-value="{{ !empty($q->risk_value) ? $q->risk_value : '' }}">
                                <label class="form-label fw-bold">{{ $q->question_text }}</label>
                                @if($q->help_text)
                                    <small class="d-block text-muted mb-2">{{ $q->help_text }}</small>
                                @endif
                                <div class="ps-2">
                                    @if($q->answer_type === 'boolean')
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" id="q{{ $q->id }}_yes" name="answers[{{ $q->id }}]" value="yes"
                                                {{ old('answers.'.$q->id, $saved?->answer_value) === 'yes' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="q{{ $q->id }}_yes">{{ __('Yes') }}</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" id="q{{ $q->id }}_no" name="answers[{{ $q->id }}]" value="no"
                                                {{ old('answers.'.$q->id, $saved?->answer_value) === 'no' || blank($saved?->answer_value) && false ? 'checked' : '' }}>
                                            <label class="form-check-label" for="q{{ $q->id }}_no">{{ __('No') }}</label>
                                        </div>
                                    @elseif($q->answer_type === 'select')
                                        <select name="answers[{{ $q->id }}]" class="form-select" style="max-width:320px">
                                            <option value="">—</option>
                                            @foreach((array) $q->options as $opt)
                                                <option value="{{ $opt }}" {{ old('answers.'.$q->id, $saved?->answer_value) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="answers[{{ $q->id }}]" class="form-control" style="max-width:420px"
                                            value="{{ old('answers.'.$q->id, $saved?->answer_value) }}">
                                    @endif
                                </div>
                                @if(!empty($q->risk_value))
                                    <div class="mt-2 ps-2">
                                        <div class="risk-live alert alert-warning py-2 px-3 d-none" role="alert">
                                            <i class="ti ti-alert-triangle me-1"></i>{{ __('Risk flagged — acquisition will be blocked unless an override reason is documented below.') }}
                                        </div>
                                        <label class="small text-muted">{{ __('If risk flagged: override requires a documented reason') }}</label>
                                        <input type="text" name="overrides[{{ $q->id }}]" class="form-control form-control-sm mt-1"
                                            style="max-width:420px" value="{{ old('overrides.'.$q->id, $saved?->override_reason) }}"
                                            placeholder="{{ __('Override reason (leave blank to keep blocked)') }}"
                                            aria-describedby="risk-hint-{{ $q->id }}">
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">{{ __('Save Screening') }}</button>
                            <a href="{{ route('study.technologist') }}" class="btn btn-outline-secondary">{{ __('Back to Worklist') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('change', function (e) {
        const target = e.target;
        if (!target.name || target.name.indexOf('answers[') !== 0) return;
        const item = target.closest('.screening-item');
        if (!item) return;
        const riskValue = item.getAttribute('data-risk-value');
        const flagged = !!riskValue && (
            (target.type === 'radio' && target.checked && target.value === riskValue) ||
            (target.type !== 'radio' && target.value === riskValue)
        );
        item.classList.toggle('border-danger', flagged);
        const hint = item.querySelector('.risk-live');
        if (hint) hint.classList.toggle('d-none', !flagged);
    });
</script>
@endpush

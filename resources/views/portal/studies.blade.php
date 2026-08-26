@extends('layouts.portal')

@section('page-title')
    {{ __('My Studies') }}
@endsection

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1">{{ __('My Imaging Studies') }}</h1>
            <p class="text-muted mb-0">{{ __('Track your study status and download released reports.') }}</p>
        </div>
    </div>

    @forelse($studies as $study)
        @php
            $state = $study->state();
            $prep = optional($study->ServiceData)->preparation_instructions;
            $final = $study->radiologyReports->first(fn ($r) => $r->isFinal() && $r->pdf_path);
        @endphp

        {{-- Mobile card --}}
        <div class="study-card d-lg-none">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <strong>{{ optional($study->ServiceData)->name ?? '-' }}</strong>
                    <div class="small text-muted">
                        {{ optional(optional($study->ServiceData)->modality)->name }}
                        &middot; {{ \Carbon\Carbon::parse($study->date_sort)->format('d M Y') }}
                        {{ \Illuminate\Support\Str::substr((string) $study->time, 0, 5) }}
                    </div>
                </div>
                <x-ui.status-badge :state="$state" />
            </div>
            @if ($prep)
                <div class="small p-2 rounded mb-2" style="background: var(--adc-warning-soft)">
                    <i class="ti ti-info-circle me-1" aria-hidden="true"></i><strong>{{ __('Preparation required') }}</strong>
                    <div class="mt-1">{{ $prep }}</div>
                </div>
            @endif
            <div class="text-end">
                @if ($final)
                    <a href="{{ route('portal.report.download', $study->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-file-download me-1" aria-hidden="true"></i>{{ __('Download Report') }}
                    </a>
                @elseif(in_array($state, [StudyState::Acquired, StudyState::Reading], true))
                    <span class="small text-muted">{{ __('Report being prepared…') }}</span>
                @endif
            </div>
        </div>

        {{-- Desktop table row --}}
        <div class="card d-none d-lg-block mb-3">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <strong>{{ optional($study->ServiceData)->name ?? '-' }}</strong>
                        <div class="small text-muted">{{ optional(optional($study->ServiceData)->modality)->name }}</div>
                    </div>
                    <div class="col-md-2">
                        {{ \Carbon\Carbon::parse($study->date_sort)->format('d M Y') }}
                        <div class="small text-muted">{{ \Illuminate\Support\Str::substr((string) $study->time, 0, 5) }}</div>
                    </div>
                    <div class="col-md-3">
                        @if ($prep)
                            <span class="badge bg-warning text-dark mb-1">{{ __('Preparation required') }}</span>
                            <div class="small text-muted">{{ $prep }}</div>
                        @else
                            <span class="text-muted">{{ __('None needed') }}</span>
                        @endif
                    </div>
                    <div class="col-md-2"><x-ui.status-badge :state="$state" /></div>
                    <div class="col-md-2 text-end">
                        @if ($final)
                            <a href="{{ route('portal.report.download', $study->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-file-download me-1" aria-hidden="true"></i>{{ __('Download') }}
                            </a>
                        @elseif(in_array($state, [StudyState::Acquired, StudyState::Reading], true))
                            <span class="small text-muted">{{ __('Report being prepared…') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body">
                <x-ui.empty-state icon="folder" :title="__('You have no imaging studies yet.')"
                    :description="__('Studies booked at the clinic will appear here automatically.')" />
            </div>
        </div>
    @endforelse

    @if ($studies->count() >= 50)
        <p class="text-center text-muted small mt-3">{{ __('Showing your 50 most recent studies.') }}</p>
    @endif
@endsection

@extends('layouts.main')

@section('page-title')
    {{ __('Reading Worklist') }}
@endsection

@section('page-breadcrumb')
    {{ __('Radiology Workflow') }}, {{ __('Reading Worklist') }}
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                <label class="d-flex align-items-center gap-1 small">
                    <input type="checkbox" name="mine" value="1" {{ request('mine') ? 'checked' : '' }}> {{ __('Assigned to me') }}
                </label>
                <select name="modality" class="form-select form-select-sm" style="max-width:170px" aria-label="{{ __('Modality') }}">
                    <option value="">{{ __('All modalities') }}</option>
                    @foreach($modalities as $mod)
                        <option value="{{ $mod->id }}" {{ request('modality') == $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                    @endforeach
                </select>
                <select name="priority" class="form-select form-select-sm" style="max-width:150px" aria-label="{{ __('Priority') }}">
                    <option value="">{{ __('Any priority') }}</option>
                    @foreach(['stat','urgent','routine'] as $p)
                        <option value="{{ $p }}" {{ request('priority') == $p ? 'selected' : '' }}>{{ strtoupper($p) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('Studies Awaiting Interpretation') }}</h5>
            <span class="badge bg-primary">{{ $studies->total() }} {{ __('studies') }}</span>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr>
                    <th>{{ __('Acquired') }}</th>
                    <th>{{ __('Patient / MRN') }}</th>
                    <th>{{ __('Procedure') }}</th>
                    <th>{{ __('Priority') }}</th>
                    <th>{{ __('TAT') }}</th>
                    <th>{{ __('Report') }}</th>
                    <th class="text-end">{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                @forelse($studies as $study)
                    @php $latest = $study->radiologyReports->first(); $tat = $study->turnaroundHours(); @endphp
                    <tr class="{{ $study->priority === 'stat' ? 'table-danger' : '' }}">
                        <td>{{ optional($study->acquired_at)->format('d M H:i') ?? '-' }}</td>
                        <td>
                            <span class="fw-bold">{{ $study->patientDisplayName() }}</span><br>
                            <small class="text-muted">{{ optional($study->CustomerData)->mrn ?? '-' }}</small>
                        </td>
                        <td>{{ optional($study->ServiceData)->name ?? '-' }}</td>
                        <td><span class="badge {{ $study->priority === 'stat' ? 'bg-danger' : ($study->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-label-secondary') }}">{{ strtoupper($study->priority) }}</span></td>
                        <td>
                            @if($tat !== null)
                                <span class="badge {{ ($study->ServiceData?->tat_target_hours ?? 24) < $tat ? 'bg-warning text-dark' : 'bg-label-success' }}">{{ $tat }} h</span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($latest)
                                <span class="badge {{ $latest->isSigned() ? 'bg-success' : 'bg-secondary' }}">v{{ $latest->version }} · {{ ucfirst($latest->type) }}</span>
                            @else
                                <span class="badge bg-light text-dark">{{ __('Not started') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @permission('report create')
                            <a href="{{ route($latest && !$latest->isSigned() ? 'reports.edit' : 'reports.create', $latest && !$latest->isSigned() ? $latest->id : $study->id) }}"
                               class="btn btn-sm btn-primary">
                                <i class="ti ti-pencil me-1"></i>{{ !$latest ? __('Start Report') : ($latest->isSigned() ? __('Addendum') : __('Continue')) }}
                            </a>
                            @endpermission
                            @if($latest?->isSigned())
                                <a href="{{ route('reports.pdf', $latest->id) }}" class="btn btn-sm btn-outline-secondary"
                                    aria-label="{{ __('Download PDF') }}"><i class="ti ti-file-download"></i></a>
                                @if($study->state() === \App\Enums\StudyState::Reported)
                                    @permission('report release')
                                    <form method="POST" action="{{ route('reports.release', $latest->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="channel" value="email">
                                        <button type="submit" class="btn btn-sm btn-success" data-adc-confirm
                                            data-adc-confirm-message="{{ __('Release the final report to the patient (email)?') }}"
                                            data-adc-confirm-text="{{ __('Release Report') }}">
                                            <i class="ti ti-send me-1"></i>{{ __('Release') }}
                                        </button>
                                    </form>
                                    @endpermission
                                @endif
                            @endif
                            @if($study->state() === \App\Enums\StudyState::Delivered)
                                <span class="badge bg-label-success align-self-center"><i class="ti ti-check me-1"></i>{{ __('Delivered') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="ti ti-mood-smile" style="font-size:48px"></i><p class="mt-2 mb-0">{{ __('Nothing waiting — the read queue is clear.') }}</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted">{{ __('Showing :count of :total', ['count' => $studies->count(), 'total' => $studies->total()]) }}</span>
            {{ $studies->links() }}
        </div>
    </div>
@endsection

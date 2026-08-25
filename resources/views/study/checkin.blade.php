@extends('layouts.main')

@section('page-title')
    {{ __('Check-in Desk') }}
@endsection

@section('page-breadcrumb')
    {{ __('Radiology Workflow') }}, {{ __('Check-in Desk') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0">{{ __("Today's Studies") }}</h5>
                        <small class="text-muted">{{ __('Check patients in as they arrive; mark no-shows to keep the queue clean.') }}</small>
                    </div>
                    <form method="GET" action="{{ route('study.checkin') }}" class="d-flex gap-2 align-items-center">
                        <input type="date" name="date" value="{{ request('date', now()->format('Y-m-d')) }}" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Token') }}</th>
                                    <th>{{ __('Time') }}</th>
                                    <th>{{ __('Patient') }}</th>
                                    <th>{{ __('Procedure') }}</th>
                                    <th>{{ __('Modality') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('State') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studies as $study)
                                    @php $state = $study->state(); $svc = $study->ServiceData; @endphp
                                    <tr>
                                        <td><span class="badge bg-label-dark">{{ $study->token_number ? '#'.$study->token_number : '-' }}</span></td>
                                        <td>{{ \Carbon\Carbon::createFromFormat('H:i', substr($study->time, 0, 5))->format('H:i') }}</td>
                                        <td>
                                            <span class="fw-bold">{{ $study->patientDisplayName() }}</span>
                                            <br><small class="text-muted">{{ optional($study->CustomerData)->mrn ?? ($study->CustomerData?->mrn ?? '-') }}</small>
                                        </td>
                                        <td>{{ $svc?->name ?? '-' }}</td>
                                        <td>
                                            @if($svc?->modality)
                                                <span class="badge" style="background-color: {{ $svc->modality->color }}22; color: {{ $svc->modality->color }};">
                                                    {{ $svc->modality->name }}
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $study->priority === 'stat' ? 'bg-danger' : ($study->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-label-secondary') }}">
                                                {{ strtoupper($study->priority) }}
                                            </span>
                                        </td>
                                        <td><span class="badge {{ $state->color() }}">{{ $state->label() }}</span></td>
                                        <td class="text-end">
                                            <div class="d-flex gap-2 justify-content-end">
                                                @if($state === \App\Enums\StudyState::Booked)
                                                    @permission('study checkin')
                                                    <form method="POST" action="{{ route('study.checkin.do', $study->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-login me-1"></i>{{ __('Check In') }}</button>
                                                    </form>
                                                    @endpermission
                                                    @permission('study checkin')
                                                    <form method="POST" action="{{ route('study.no-show', $study->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary show_confirm"
                                                            title="{{ __('Mark No-Show') }}"><i class="ti ti-user-x"></i></button>
                                                    </form>
                                                    @endpermission
                                                @elseif($state === \App\Enums\StudyState::CheckedIn)
                                                    <span class="text-muted">{{ __('Waiting for technologist') }}</span>
                                                    @permission('appointment manage')
                                                    <a href="{{ route('appointment.details', $study->id) }}" class="btn btn-sm btn-outline-info"><i class="ti ti-eye"></i></a>
                                                    @endpermission
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="ti ti-calendar-off" style="font-size:48px"></i>
                                            <p class="mt-2 mb-0">{{ __('No studies scheduled for this date.') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

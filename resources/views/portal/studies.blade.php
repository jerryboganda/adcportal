@extends('layouts.main')

@section('page-title')
    {{ __('My Studies') }}
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('My Imaging Studies') }}</h5>
                    <small class="text-muted">{{ __('Track your study status and download released reports.') }}</small>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Procedure') }}</th>
                            <th>{{ __('Preparation') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Report') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse($studies as $study)
                            @php $state = $study->state(); $prep = optional($study->ServiceData)->preparation_instructions; @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($study->date_sort)->format('d M Y') }}
                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::substr((string) $study->time, 0, 5) }}</small></td>
                                <td>{{ optional($study->ServiceData)->name ?? '-' }}
                                    <br><small class="text-muted">{{ optional(optional($study->ServiceData)->modality)->name }}</small></td>
                                <td style="max-width:260px">
                                    @if($prep)
                                        <span class="badge bg-warning text-dark mb-1">{{ __('Preparation required') }}</span>
                                        <div class="small text-muted">{{ $prep }}</div>
                                    @else
                                        <span class="text-muted">{{ __('None needed') }}</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $state->color() }}">{{ $state->label() }}</span></td>
                                <td class="text-end">
                                    @php $final = $study->radiologyReports->first(fn($r) => $r->isFinal()); @endphp
                                    @if($final && ($final->pdf_path || true))
                                        <a href="{{ route('portal.report.download', $study->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-file-download me-1"></i>{{ __('Download') }}
                                        </a>
                                    @elseif(in_array($state, [StudyState::Acquired, StudyState::Reading], true))
                                        <span class="text-muted">{{ __('Being prepared…') }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">
                                <i class="ti ti-folder" style="font-size:44px"></i>
                                <p class="mt-2 mb-0">{{ __('You have no imaging studies yet.') }}</p>
                            </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

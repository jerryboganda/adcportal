@extends('layouts.main')

@section('page-title')
    {{ __('Modalities & Rooms') }}
@endsection

@section('page-breadcrumb')
    {{ __('Masters') }}, {{ __('Modalities & Rooms') }}
@endsection

@section('content')
    <div class="row">
        {{-- Modalities --}}
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header"><h5>{{ __('Imaging Modalities') }}</h5></div>
                <div class="card-body">
                    @permission('modality create')
                    <form method="POST" action="{{ route('modality.store') }}" class="row g-2 mb-3 align-items-end">
                        @csrf
                        <div class="col-4"><input name="name" class="form-control form-control-sm" placeholder="{{ __('Name e.g. MRI') }}" required></div>
                        <div class="col-3"><input name="code" class="form-control form-control-sm" placeholder="{{ __('Code MR') }}" maxlength="10" required></div>
                        <div class="col-3"><input type="number" name="buffer_minutes" class="form-control form-control-sm" placeholder="{{ __('Buffer') }}" min="0"></div>
                        <div class="col-2"><button class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
                    </form>
                    @endpermission

                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{__('Name')}}</th><th>{{__('Code')}}</th><th>{{__('Procedures')}}</th><th>{{__('Buffer')}}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($modalities as $modality)
                            <tr>
                                <td><span class="badge rounded-pill" style="background: {{ $modality->color }}22; color: {{ $modality->color }};">{{ $modality->name }}</span></td>
                                <td>{{ $modality->code }}</td>
                                <td><span class="badge bg-label-info">{{ $modality->procedures_count }}</span></td>
                                <td>{{ $modality->buffer_minutes }} {{ __('min') }}</td>
                                <td class="text-end">
                                    @permission('modality delete')
                                    <form method="POST" action="{{ route('modality.destroy', $modality->id) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger show_confirm"><i class="ti ti-trash"></i></button>
                                    </form>
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Add your first modality (e.g. X-ray, Ultrasound, CT).') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Rooms --}}
        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><h5>{{ __('Scan Rooms') }}</h5></div>
                <div class="card-body">
                    @permission('room create')
                    <form method="POST" action="{{ route('room.store') }}" class="row g-2 mb-3 align-items-end">
                        @csrf
                        <div class="col-3"><input name="name" class="form-control form-control-sm" placeholder="{{ __('Room name') }}" required></div>
                        <div class="col-3">
                            <select name="modality_id" class="form-select form-select-sm" required>
                                <option value="">{{ __('Modality…') }}</option>
                                @foreach($modalities as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-2"><input type="number" name="capacity_per_slot" class="form-control form-control-sm" value="1" min="1"></div>
                        <div class="col-2"><button class="btn btn-sm btn-primary w-100">{{ __('Add') }}</button></div>
                    </form>
                    @endpermission

                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>{{__('Room')}}</th><th>{{__('Modality')}}</th><th>{{__('Capacity / slot')}}</th><th>{{__('Status')}}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($rooms as $room)
                            <tr>
                                <td class="fw-bold">{{ $room->name }}</td>
                                <td>{{ optional($room->modality)->name ?? '-' }}</td>
                                <td>{{ $room->capacity_per_slot }}</td>
                                <td><span class="badge {{ $room->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $room->is_active ? __('Active') : __('Off') }}</span></td>
                                <td class="text-end">
                                    @permission('room delete')
                                    <form method="POST" action="{{ route('room.destroy', $room->id) }}" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger show_confirm"><i class="ti ti-trash"></i></button>
                                    </form>
                                    @endpermission
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No scan rooms configured.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Equipment Downtime') }}</h5>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#downtime-form">{{ __('+ Record window') }}</button>
                </div>
                <div class="card-body">
                    <div id="downtime-form" class="collapse mb-3">
                        <form method="POST" action="{{ route('room.downtime.store') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <select name="room_id" class="form-select form-select-sm" required>
                                    <option value="">{{ __('Room…') }}</option>
                                    @foreach($rooms as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input type="datetime-local" name="starts_at" class="form-control form-control-sm" required></div>
                            <div class="col-md-3"><input type="datetime-local" name="ends_at" class="form-control form-control-sm" required></div>
                            <div class="col-md-2"><input name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason') }}"></div>
                            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">OK</button></div>
                        </form>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse($downtimes as $dt)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    <strong>{{ optional($dt->room)->name ?? ('Room #'.$dt->room_id) }}</strong>
                                    · {{ $dt->starts_at->format('d M H:i') }} → {{ $dt->ends_at->format('d M H:i') }}
                                    @if($dt->reason) <small class="text-muted">({{ $dt->reason }})</small>@endif
                                </span>
                                <form method="POST" action="{{ route('room.downtime.destroy', $dt->id) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger show_confirm"><i class="ti ti-x"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">{{ __('No scheduled downtime.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

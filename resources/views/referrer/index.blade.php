@extends('layouts.main')

@section('page-title')
    {{ __('Referring Doctors') }}
@endsection

@section('page-breadcrumb')
    {{ __('Referring Doctors') }}
@endsection

@section('page-action')
    <div class="d-flex col-auto gap-2">
        @permission('referrer create')
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
            data-title="{{ __('Create New Referring Doctor') }}" data-url="{{ route('referrer.create') }}"
            data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}">
            <i class="ti ti-plus" aria-hidden="true"></i>
        </a>
        @endpermission
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Referring Doctors List') }}</h5>
                    <small class="text-muted">{{ __('Manage doctors who refer patients to your diagnostic centre') }}</small>
                </div>
                <div class="card-body">
                    <div class="booking-data-table">
                        <table class="table table-hover" id="referrers-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Specialty') }}</th>
                                    <th>{{ __('Clinic') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Appointments') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($referrers as $key => $referrer)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>
                                            <span class="fw-bold">{{ $referrer->name }}</span>
                                        </td>
                                        <td>{{ $referrer->specialty ?? '-' }}</td>
                                        <td>{{ $referrer->clinic ?? '-' }}</td>
                                        <td>{{ $referrer->phone ?? '-' }}</td>
                                        <td>{{ $referrer->email ?? '-' }}</td>
                                        <td>
                                            @if($referrer->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $referrer->appointments()->count() }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex gap-2 justify-content-end">
                                                @permission('referrer edit')
                                                <a href="#" class="btn btn-sm btn-info" data-ajax-popup="true" data-size="md"
                                                    data-title="{{ __('Edit Referring Doctor') }}" 
                                                    data-url="{{ route('referrer.edit', $referrer->id) }}"
                                                    data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}">
                                                    <i class="ti ti-pencil" aria-hidden="true"></i>
                                                </a>
                                                @endpermission
                                                @permission('referrer delete')
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['referrer.destroy', $referrer->id], 'class' => 'd-inline']) !!}
                                                <button type="submit" class="btn btn-sm btn-danger show_confirm"
                                                    data-bs-toggle="tooltip" data-bs-original-title="{{ __('Delete') }}">
                                                    <i class="ti ti-trash" aria-hidden="true"></i>
                                                </button>
                                                {!! Form::close() !!}
                                                @endpermission
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti ti-users-group" style="font-size: 48px;" aria-hidden="true"></i>
                                                <p class="mt-2">{{ __('No referring doctors found.') }}</p>
                                                @permission('referrer create')
                                                <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
                                                    data-title="{{ __('Create New Referring Doctor') }}" data-url="{{ route('referrer.create') }}">
                                                    <i class="ti ti-plus me-1"></i>{{ __('Add First Referrer') }}
                                                </a>
                                                @endpermission
                                            </div>
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

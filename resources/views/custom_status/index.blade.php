@extends('layouts.main')

@section('page-title')
    {{ __('Custom Status') }}
@endsection
@section('page-breadcrumb')
{{ __('Custom Status') }}
@endsection



@section('page-action')
<div>
    @permission('status create')
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="md"
            data-title="{{ __('Create New Status') }}" data-url="{{ route('custom-status.create') }}" data-bs-toggle="tooltip"
            data-bs-original-title="{{ __('Create') }}" aria-label="{{ __('Create New Status') }}">
            <i class="ti ti-plus" aria-hidden="true"></i>
        </a>
    @endpermission
</div>

@endsection
@section('content')
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="card">
                <div class="card-header card-body table-border-style">
                    <div class="booking-data-table">
                        <table class="table mb-0 pc-dt-simple" id="pc-dt-simple">
                            <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th style="min-width:250px;">{{ __('Name') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($statuses as $status)
                                <tr>
                                    <th scope="row">{{$status->id}}</th>
                                    <td class="status_badge"><span class="white-space" style="background-color: #{{!empty($status->status_color) ?  $status->status_color : '5bc0de' }};">{{$status->title}} </span></td>
                                    <td>
                                        <div class="d-flex" style="justify-content:end;">
                                        @permission('status update')
                                        <div class="action-btn me-2">
                                            <a href="#" class="btn btn-sm  bg-info d-inline align-items-center"
                                                data-url="{{ route('custom-status.edit', $status->id) }}"
                                                data-ajax-popup="true"
                                                data-title="{{ __('Edit Status') }}" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Edit') }}" aria-label="{{ __('Edit Status') }}">
                                                <span class="text-white"> <i class="ti ti-pencil" aria-hidden="true"></i></span></a>
                                        </div>
                                        @endpermission
                                        @permission('status delete')
                                                <div class="action-btn">
                                                    <form method="POST" action="{{route('custom-status.destroy',$status->id)}}" id="user-form-{{$status->id}}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input name="_method" type="hidden" value="DELETE">
                                                        <button type="button" class="btn btn-sm bg-danger d-inline align-items-center show_confirm" data-bs-toggle="tooltip"
                                                        title='{{ __('Delete') }}' aria-label="{{ __('Delete') }}">
                                                            <span class="text-white"> <i class="ti ti-trash" aria-hidden="true"></i></span>
                                                        </button>
                                                    </form>
                                                </div>
                                        @endpermission
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection


@push('scripts')
<script src="{{ asset('js/jscolor.js') }}"></script>
@endpush

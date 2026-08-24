@extends('layouts.main')

@section('page-title')
    {{ __('Subscribers') }}
@endsection
@section('page-breadcrumb')
    {{ $business->name }},
    {{ __('Subscribers') }}
@endsection

@section('page-action')
@endsection

@section('content')
    <!-- [ Main Content ] start -->
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <x-datatable :dataTable="$dataTable" />
        </div>
    </div>
    <!-- [ Main Content ] end -->
@endsection

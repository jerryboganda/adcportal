@extends('layouts.main')
@section('page-title')
    {{ __('Manage Roles') }}
@endsection
@section('page-breadcrumb')
    {{ __('Roles') }}
@endsection
@section('page-action')
    @permission('roles create')
        <div>
            <a href="#" class="btn btn-sm btn-primary" data-url="{{ route('roles.create') }}" data-size="xl"
                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Create') }}" data-ajax-popup="true"
                data-title="{{ __('Create New Role') }}" aria-label="{{ __('Create New Role') }}">
                <i class="ti ti-plus" aria-hidden="true"></i>
            </a>
        </div>
    @endpermission
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="booking-data-table">
                        <table class="table mb-0 pc-dt-simple" id="assets">
                            <thead>
                                <tr>
                                    <th> {{ __('Role') }}</th>
                                    <th> {{ __('Permissions') }}</th>
                                    <th class="text-end" width="200px"> {{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roles as $role)
                                    @php
                                        $permissions = $role->permissions()->get();
                                    @endphp
                                    <tr>
                                        <td width="150px">{{ $role->name }}</td>
                                        <td class="permission" style="min-width: 320px;">
                                            @forelse ($permissions as $permission)
                                                <span class="badge p-2 m-1 px-3 bg-primary">
                                                    {{ $permission->name }}</span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-end">
                                                @permission('roles edit')
                                                    <div class="action-btn me-2">
                                                        <a data-url="{{ route('roles.edit', $role->id) }}" data-size="xl"
                                                            data-ajax-popup="true" data-title="{{ __('Edit Permission') }}"
                                                            class="btn btn-outline bg-info  btn-sm blue-madison" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Edit">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endpermission
                                                @if (!in_array($role->name, \App\Models\User::$not_edit_role))
                                                    @permission('roles delete')
                                                        <div class="action-btn" data-bs-toggle="tooltip"
                                                            data-bs-placement="bottom" title=""
                                                            data-bs-original-title="Delete" aria-describedby="tooltip434956">
                                                            {!! Form::open([
                                                                'method' => 'DELETE',
                                                                'route' => ['roles.destroy', $role->id],
                                                                'id' => 'delete-form-' . $role->id,
                                                            ]) !!}

                                                            <a type="submit"
                                                                class="btn btn-sm bg-danger align-items-center show_confirm"
                                                                data-toggle="tooltip" title=""
                                                                data-original-title="Delete">
                                                                <i class="ti ti-trash text-white"></i>
                                                            </a>
                                                            {!! Form::close() !!}
                                                        </div>
                                                    @endpermission
                                                @endif
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
    <script type="text/javascript">

        function Checkall(module = null) {
            var ischecked = $("#checkall-" + module).prop('checked');
            if (ischecked == true) {
                $('.checkbox-' + module).prop('checked', true);
            } else {
                $('.checkbox-' + module).prop('checked', false);
            }

            // Get all checkboxes with IDs that start with 'module_checkbox_' and include the module
            var checkboxes = document.querySelectorAll('input[id^="module_checkbox_"]');

            // Check or uncheck all checkboxes based on the 'checkall' checkbox state
            checkboxes.forEach(function(checkbox) {
                var check = $("#checkall-" + module).prop('checked');
                if (checkbox.id.includes(module)) {
                    checkbox.checked = check
                }
            });

            // Call CheckModule to update the module checkbox state
            CheckModule('module_checkbox_' + module);
        }

        function CheckModule(cl = null) {
            var ischecked = $("#" + cl).prop('checked');
            if (ischecked == true) {
                $('.' + cl).find("input[type=checkbox]").prop('checked', true);
            } else {
                $('.' + cl).find("input[type=checkbox]").prop('checked', false);
            }
        }

        function CheckPermission(cl = null, module = null) {
            console.log(cl , module);
            var ischecked = $("#" + cl).prop('checked');
            var allChecked = true;

            // Check if all permissions for the given module are checked
            $('.' + module).find("input[type=checkbox]").each(function() {
                if (!$(this).prop('checked')) {
                    allChecked = false;
                    return false; // Exit the loop
                }
            });

            // Update the module checkbox based on the state of permissions
            if (allChecked) {
                $('#' + module).prop('checked', true);
            } else {
                $('#' + module).prop('checked', false);
            }
        }

        $(document).ready(function() {
            // Attach the CheckPermission function to all permission checkboxes
            $(document).on('change', 'input[type=checkbox]', function() {
                var id = $(this).attr('id');
                var module = $(this).data('module');
                CheckPermission(id, module);
            });
        });
    </script>
@endpush

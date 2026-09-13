<?php

namespace App\Models;

use Laratrust\Models\Permission as PermissionModel;

class Permission extends PermissionModel
{
    // See Role: the parent's $fillable drops app columns (guard_name/module/created_by).
    public $fillable = ['name', 'display_name', 'description', 'guard_name', 'module', 'created_by'];

    public $guarded = [];
}

<?php

namespace App\Models;

use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    // The parent's non-empty $fillable silently drops columns like created_by
    // even when $guarded is empty — redeclare it with the columns this app uses.
    public $fillable = ['name', 'display_name', 'description', 'guard_name', 'module', 'created_by'];

    public $guarded = [];
}

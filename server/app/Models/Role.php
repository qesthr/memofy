<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description', 'status', 'permission_ids', 'department'];

    protected $casts = [
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, null, 'role_ids', 'permission_ids');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission($permissionName)
    {
        if (empty($this->permission_ids)) {
            return false;
        }
        return in_array($permissionName, $this->permission_ids);
    }
}

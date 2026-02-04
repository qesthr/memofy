<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'label', 'description'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, null, 'permission_ids', 'role_ids');
    }
}

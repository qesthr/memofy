<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description', 'status'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, null, 'role_ids', 'permission_ids');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

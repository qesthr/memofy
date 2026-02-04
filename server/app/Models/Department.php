<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MemoTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'signature_id',
        'department_id',
        'priority',
        'content',
        'template_data'
    ];

    protected $casts = [
        'template_data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signature()
    {
        return $this->belongsTo(UserSignature::class, 'signature_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

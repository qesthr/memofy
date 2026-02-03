<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_title',
        'display_name',
        'image_url',
        'is_active',
        'order',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

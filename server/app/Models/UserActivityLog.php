<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_email',
        'actor_role',
        'actor_department',
        'action', // user_login, memo_created, etc.
        'target', // Clean human readable target description
        'target_id', // ID of the target object (optional)
        'description',
        'details', // JSON payload with more info
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class UserInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role',
        'department',
        'token',
        'expires_at',
        'invited_by',
        'user_id',
        'status' // pending, accepted, expired
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
    
    // Check if valid
    public function isValid()
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}

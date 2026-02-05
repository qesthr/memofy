<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class UserLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'locked_by_id',
        'locked_by_email',
        'locked_by_name',
        'locked_at',
        'expires_at',
        'resource_type',
        'resource_id'
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isLockedBy($adminId)
    {
        return $this->locked_by_id === $adminId;
    }

    public function minutesRemaining()
    {
        if ($this->isExpired()) {
            return 0;
        }
        return $this->expires_at->diffInMinutes(now());
    }

    public function secondsRemaining()
    {
        if ($this->isExpired()) {
            return 0;
        }
        return $this->expires_at->diffInSeconds(now());
    }
}

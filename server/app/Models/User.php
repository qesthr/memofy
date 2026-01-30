<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password_hash',
        'department',
        'is_active',
        'created_by',
        'role', // Keeping 'role' for backward compatibility or simple role check
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    // Relationships

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
                    ->withPivot('assigned_at', 'assigned_by');
    }

    public function sentMemos()
    {
        return $this->hasMany(Memo::class, 'sender_id', 'user_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(MemoAcknowledgment::class, 'recipient_id', 'user_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(UserActivityLog::class, 'user_id', 'user_id');
    }

    public function memoLogs()
    {
        return $this->hasMany(MemoLog::class, 'performed_by', 'user_id');
    }
}

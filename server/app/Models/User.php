<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'google_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'role',
        'department',
        'employee_id',
        'profile_picture',
        'is_active',
        'login_attempts',
        'lock_until',
        'violation_count',
        'last_login',
        'last_failed_login',
        'google_calendar_token',
        'google_calendar_refresh_token',
        'google_calendar_token_expires_at',
        'google_calendar_email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'last_failed_login' => 'datetime',
        'lock_until' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Compare password (wrapper for Hash::check)
     */
    public function comparePassword($password)
    {
        return \Hash::check($password, $this->password);
    }

    /**
     * Increment login attempts and handle lockout logic
     */
    public function incrementLoginAttempts()
    {
        $this->increment('login_attempts');
        $this->update(['last_failed_login' => now()]);

        if ($this->login_attempts >= 5) {
            $this->lockAccount();
        }
    }

    /**
     * Reset login attempts
     */
    public function resetLoginAttempts()
    {
        $this->update([
            'login_attempts' => 0,
            'lock_until' => null
        ]);
    }

    /**
     * Lock account temporarily
     */
    public function lockAccount()
    {
        // Progressive lockout logic could go here, currently fixed 5 mins
        $lockoutMinutes = 5;
        $this->update([
            'lock_until' => now()->addMinutes($lockoutMinutes),
            'violation_count' => $this->violation_count + 1
        ]);
    }

    // Relationships

    public function sentMemos()
    {
        return $this->hasMany(Memo::class, 'sender_id');
    }

    public function receivedMemos()
    {
        return $this->hasMany(Memo::class, 'recipient_id');
    }

    public function createdMemos()
    {
        return $this->hasMany(Memo::class, 'created_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(UserActivityLog::class, 'actor_id');
    }

    public function eventInvitations()
    {
        return $this->hasMany(CalendarEventParticipant::class);
    }
}

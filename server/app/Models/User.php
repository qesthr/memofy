<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Auth\User as Authenticatable;
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
        'department_id',
        'theme',
        'reset_code',
        'reset_code_expires_at',
        'role_id'
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
     * The attributes that should be cast for serialization.
     *
     * @var array<string, string>
     */
    protected $appends = ['permissions'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login' => 'datetime',
        'last_failed_login' => 'datetime',
        'lock_until' => 'datetime',
        'password' => 'hashed',
        'department_id' => 'string',
        'reset_code_expires_at' => 'datetime',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function assignedRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function departmentModel()
    {
        return $this->belongsTo(Department::class, 'department_id');
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

    /**
     * Check if user has a specific permission
     */
    public function hasPermissionTo($permissionName)
    {
        $permissions = $this->permissions;
        return is_array($permissions) && in_array($permissionName, $permissions);
    }

    /**
     * Get permission names for the user
     */
    public function getPermissionsAttribute()
    {
        // 1. Determine effective role name (case-insensitive)
        $roleField = strtolower($this->getAttribute('role') ?? '');
        $roleModel = $this->assignedRole;
        $roleNameFromModel = $roleModel ? strtolower($roleModel->name ?? '') : '';

        // 2. Admin master override
        if ($roleField === 'admin' || $roleNameFromModel === 'admin' || $roleField === 'super_admin') {
            return \App\Models\Permission::pluck('name')->toArray();
        }

        // 3. Resolve Role Model if not loaded
        if (!$roleModel && $roleField) {
            $roleModel = Role::where('name', 'i-like', $roleField)->first();
        }
        
        if (!$roleModel || !$roleModel->permission_ids) {
            return [];
        }

        // 4. Fetch permission names based on IDs
        return \App\Models\Permission::whereIn('_id', $roleModel->permission_ids)
                         ->pluck('name')
                         ->toArray();
    }
}

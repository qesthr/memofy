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
        'status',
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
        'role_id',
        'permission_ids',
        'bio',
        'archived_at',
        'archived_by'
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
    /**
     * The attributes that should be appended when serializing.
     */
    protected $appends = ['permissions', 'full_name'];

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
        'archived_at' => 'datetime'
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if the user has an administrative role.
     */
    public function isAdmin()
    {
        $role = strtolower($this->role ?? '');
        return in_array($role, ['admin', 'superadmin', 'super_admin']);
    }

    public function assignedRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function departmentModel()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    const MAX_LOGIN_ATTEMPTS = 5;

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

        if ($this->login_attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->lockAccount();
            return true; // Locked
        }
        return false; // Not locked yet
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
        // Read lockout duration from system settings (admin-configurable), default 15 mins
        $lockoutMinutes = intval(\App\Models\SystemSetting::get('login_lockout_minutes', 15));
        $lockoutMinutes = max(1, $lockoutMinutes); // Ensure at least 1 minute

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
        // Global bypass for Admin/Superadmin
        $roleName = strtolower($this->getAttribute('role') ?? '');
        if ($roleName === 'admin' || $roleName === 'superadmin' || $roleName === 'super_admin') {
            return true;
        }

        $permissions = $this->permissions;
        return is_array($permissions) && in_array($permissionName, $permissions);
    }

    /**
     * Get permission names for the user
     */
    public function getPermissionsAttribute()
    {
        $cacheKey = "user_permissions_{$this->id}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHour(), function () {
            // 1. Check for user-specific permissions (Override)
            if (!empty($this->permission_ids)) {
                return $this->permission_ids;
            }

            // 2. Fallback to Role permissions 
            $roleModel = $this->assignedRole;

            // 3. Resolve Role Model if not loaded (fallback for legacy role field)
            if (!$roleModel) {
                $roleField = strtolower($this->getAttribute('role') ?? '');
                if ($roleField) {
                    $roleModel = Role::where('name', $roleField)->first();
                }
            }
            
            // 4. Return permission names
            if (!$roleModel || !$roleModel->permission_ids) {
                return [];
            }

            return $roleModel->permission_ids;
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'memo_approved',
        'memo_rejected',
        'memo_received',
        'memo_acknowledged',
        'calendar_invitation',
        'calendar_updated',
        'calendar_response',
        'profile_updated',
        'calendar_secretary_created',
        'email_notifications',
        'push_notifications'
    ];

    protected $casts = [
        'memo_approved' => 'boolean',
        'memo_rejected' => 'boolean',
        'memo_received' => 'boolean',
        'memo_acknowledged' => 'boolean',
        'calendar_invitation' => 'boolean',
        'calendar_updated' => 'boolean',
        'calendar_response' => 'boolean',
        'profile_updated' => 'boolean',
        'calendar_secretary_created' => 'boolean',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get or create default preferences for a user
     */
    public static function getDefaultForUser($userId)
    {
        // Convert ObjectId to string if needed
        $userIdString = (string) $userId;
        
        $preference = self::where('user_id', $userIdString)->first();
        
        if (!$preference) {
            $preference = self::create([
                'user_id' => $userIdString,
                'memo_approved' => true,
                'memo_rejected' => true,
                'memo_received' => true,
                'memo_acknowledged' => true,
                'calendar_invitation' => true,
                'calendar_updated' => true,
                'calendar_response' => true,
                'profile_updated' => true,
                'calendar_secretary_created' => true,
                'email_notifications' => true,
                'push_notifications' => true
            ]);
        }
        
        return $preference;
    }

    /**
     * Check if user should receive a specific notification type
     */
    public function shouldReceive(string $type): bool
    {
        $mapping = [
            'memo.approved' => $this->memo_approved,
            'memo.rejected' => $this->memo_rejected,
            'memo.received' => $this->memo_received,
            'memo.acknowledged' => $this->memo_acknowledged,
            'calendar.invitation' => $this->calendar_invitation,
            'calendar.updated' => $this->calendar_updated,
            'calendar.response' => $this->calendar_response,
            'profile.updated' => $this->profile_updated,
            'calendar.secretary_created' => $this->calendar_secretary_created
        ];

        return $mapping[$type] ?? false;
    }

    /**
     * Check if user wants email notifications
     */
    public function wantsEmail(): bool
    {
        return $this->email_notifications;
    }

    /**
     * Check if user wants push notifications
     */
    public function wantsPush(): bool
    {
        return $this->push_notifications;
    }
}

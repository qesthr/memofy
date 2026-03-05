<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $collection = 'notifications';

    // Use MongoDB's default _id as primary key to avoid duplicate key errors
    protected $primaryKey = '_id';

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public $timestamps = true;

    // Notification type constants
    public const TYPE_MEMO_APPROVED = 'memo.approved';
    public const TYPE_MEMO_REJECTED = 'memo.rejected';
    public const TYPE_MEMO_RECEIVED = 'memo.received';
    public const TYPE_MEMO_ACKNOWLEDGED = 'memo.acknowledged';
    public const TYPE_CALENDAR_INVITATION = 'calendar.invitation';
    public const TYPE_CALENDAR_UPDATED = 'calendar.updated';
    public const TYPE_CALENDAR_RESPONSE = 'calendar.response';
    public const TYPE_PROFILE_UPDATED = 'profile.updated';
    public const TYPE_CALENDAR_SECRETARY_CREATED = 'calendar.secretary_created';
    public const TYPE_MEMO_REMINDER = 'memo.reminder';
    public const TYPE_MEMO_PENDING_APPROVAL = 'memo.pending_approval';

    /**
     * Get the notifiable entity that the notification belongs to.
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Get formatted created_at
     */
    public function getFormattedDate(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get notification icon based on type
     */
    public function getIcon(): string
    {
        return match($this->type) {
            self::TYPE_MEMO_APPROVED => '✅',
            self::TYPE_MEMO_REJECTED => '❌',
            self::TYPE_MEMO_RECEIVED => '📬',
            self::TYPE_MEMO_ACKNOWLEDGED => '👁️',
            self::TYPE_CALENDAR_INVITATION => '📅',
            self::TYPE_CALENDAR_UPDATED => '🔄',
            self::TYPE_CALENDAR_RESPONSE => '📨',
            self::TYPE_PROFILE_UPDATED => '👤',
            self::TYPE_CALENDAR_SECRETARY_CREATED => '📅',
            default => '🔔'
        };
    }

    /**
     * Get the link associated with the notification
     */
    public function getLink(): string
    {
        $data = $this->data;
        
        if (isset($data['link'])) {
            return $data['link'];
        }

        if (isset($data['memo_id'])) {
            return '/memos/' . $data['memo_id'];
        }
        
        if (isset($data['event_id'])) {
            return '/calendar?event=' . $data['event_id'];
        }
        
        return '/notifications';
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

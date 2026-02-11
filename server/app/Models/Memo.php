<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Memo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'sender_id',
        'recipient_id',
        'recipient_ids',
        'department_id',
        'subject',
        'message',
        'priority', // high, medium, low
        'status', // draft, sent, read, pending_approval, rejected, archived, deleted
        'attachments',
        'version',
        'is_draft',
        'user_id',
        'scheduled_send_at',
        'schedule_end_at',
        'all_day_event',
        'signature_id',
        // Approval workflow fields
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'signature_ids',
        'signature_positions'
    ];

    protected $casts = [
        'attachments' => 'array',
        'recipient_ids' => 'array',
        'signature_ids' => 'array',
        'signature_positions' => 'array',
        'is_draft' => 'boolean',
        'version' => 'integer',
        'scheduled_send_at' => 'datetime',
        'schedule_end_at' => 'datetime',
        'all_day_event' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Relationships

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function recipients()
    {
        // For MongoDB, we can use whereIn if it's an array of IDs
        return User::whereIn('_id', $this->recipient_ids ?? []);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    // Approval relationships
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    
    public function signature()
    {
        return $this->belongsTo(UserSignature::class, 'signature_id');
    }
    
    public function acknowledgments()
    {
        return $this->hasMany(MemoAcknowledgment::class, 'memo_id');
    }
    
    public function logs()
    {
        return $this->hasMany(MemoLog::class, 'memo_id');
    }
    
    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }
}

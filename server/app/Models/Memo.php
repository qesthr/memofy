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
        'subject',
        'message',
        'priority', // urgent, high, normal, low
        'status', // draft, sent, read, archived, deleted
        'attachments',
        'version',
        'is_draft',
        'user_id',
        'scheduled_send_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_draft' => 'boolean',
        'version' => 'integer',
        'scheduled_send_at' => 'datetime',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }
}

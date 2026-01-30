<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    use HasFactory;

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
        'user_id'
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_draft' => 'boolean',
        'version' => 'integer',
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

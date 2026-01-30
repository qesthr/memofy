<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start',
        'end',
        'all_day',
        'category',
        'description',
        'participants',
        'memo_id',
        'created_by',
        'status',
        'google_calendar_event_ids'
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'all_day' => 'boolean',
        'participants' => 'array',
        'google_calendar_event_ids' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }
}

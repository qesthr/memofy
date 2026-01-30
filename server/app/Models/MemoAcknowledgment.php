<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemoAcknowledgment extends Model
{
    use HasFactory;

    protected $primaryKey = 'acknowledgment_id';
    public $timestamps = false; // Based on schema, fields are acknowledged_at, sent_at

    protected $fillable = [
        'memo_id',
        'recipient_id',
        'is_acknowledged',
        'acknowledged_at',
        'sent_at'
    ];

    protected $casts = [
        'is_acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class, 'memo_id', 'memo_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id', 'user_id');
    }
}

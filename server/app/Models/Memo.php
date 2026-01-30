<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memo extends Model
{
    use HasFactory;

    protected $primaryKey = 'memo_id';

    protected $fillable = [
        'subject',
        'content',
        'sender_id',
        'priority',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function logs()
    {
        return $this->hasMany(MemoLog::class, 'memo_id', 'memo_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(MemoAcknowledgment::class, 'memo_id', 'memo_id');
    }
}

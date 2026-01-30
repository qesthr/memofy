<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemoLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'log_id';
    public $timestamps = false; // Based on schema, only created_at exists, managed manually or via event

    protected $fillable = [
        'memo_id',
        'action',
        'performed_by',
        'details',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class, 'memo_id', 'memo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'performed_by', 'user_id');
    }
}

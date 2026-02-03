<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class RollbackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'operation_type',
        'before_state',
        'after_state',
        'performed_by',
        'timestamp', // Manually set or use created_at
        'status',
        'rolled_back_by',
        'rolled_back_at',
        'rollback_reason'
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'timestamp' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function rollbackPerformer()
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}

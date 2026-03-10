<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Archive extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'archives';

    protected $fillable = [
        'item_id',
        'item_type',
        'archived_by',
        'archived_at',
        'sender_id',
        'recipient_id',
        'created_by',
        'role',
        'department',
        'department_id',
        'payload'
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'payload' => 'array'
    ];
}

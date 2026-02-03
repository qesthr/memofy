<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserSignature extends Model
{
    protected $table = 'user_signatures';

    protected $fillable = ['user_id', 'name', 'signature_data', 'is_default'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

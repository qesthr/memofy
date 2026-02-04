<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by'
    ];

    protected $casts = [
        'value' => 'array', // Will auto-serialize/unserialize JSON
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get setting value by key
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value
     */
    public static function set($key, $value, $updatedBy = null)
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $updatedBy,
                'updated_at' => now()
            ]
        );
    }
}

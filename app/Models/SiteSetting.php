<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends BaseModel
{
    protected $table = 'site_settings';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}

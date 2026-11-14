<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = self::query()->find($key);

        return $record?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value]
        );
    }
}

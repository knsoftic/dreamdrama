<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private static ?array $loaded = null;

    public static function defaults(): array
    {
        $defaults = [];
        foreach (config('platform.settings', []) as $group) {
            foreach ($group['fields'] as $key => $field) {
                $defaults[$key] = $field['default'] ?? null;
            }
        }

        return $defaults;
    }

    public static function allValues(): array
    {
        if (static::$loaded === null) {
            $saved = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());
            static::$loaded = array_merge(static::defaults(), $saved);
        }

        return static::$loaded;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function flush(): void
    {
        Cache::forget('settings.all');
        static::$loaded = null;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings.all');
        static::$loaded = null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Small key/value store for admin-editable site config. Read on every
 * page render (the WhatsApp button lives in the layout), so values are
 * cached forever and the cache key is cleared whenever one is written.
 */
#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    private const CACHE_KEY = 'site_settings.all';

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all()
        );

        return $all[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}

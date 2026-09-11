<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'checkout_enabled', 'currency_code'])]
class Country extends Model
{
    protected function casts(): array
    {
        return [
            'checkout_enabled' => 'boolean',
        ];
    }

    /**
     * Countries a customer can pick as a delivery address at checkout -
     * distinct from the full world list used at registration ("country
     * of residence"), since Selbuildi doesn't ship building materials
     * everywhere.
     *
     * @return array<string, string>
     */
    public static function checkoutOptions(): array
    {
        return static::where('checkout_enabled', true)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }
}

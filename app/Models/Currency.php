<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'country_label', 'rate_to_xaf', 'rate_source', 'rate_fetched_at', 'is_supported', 'sort_order'])]
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'rate_to_xaf' => 'decimal:6',
            'rate_fetched_at' => 'datetime',
            'is_supported' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>  code => "NGN - Nigerian Naira"
     */
    public static function supportedOptions(): array
    {
        return static::where('is_supported', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => $currency->label()])
            ->all();
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Converts a canonical XAF integer amount into this currency, rounded
     * to a whole unit. Amounts stay whole numbers across the board
     * (matching how XAF - the one currency every order's real ledger
     * amount is always stored in - has no subunit at all), rather than
     * introducing fractional-cent precision only some currencies would
     * actually use.
     */
    public function convertFromXaf(int $xafAmount): int
    {
        if ($this->code === 'XAF') {
            return $xafAmount;
        }

        return (int) round($xafAmount / (float) $this->rate_to_xaf);
    }

    public function format(int $xafAmount): string
    {
        $converted = $this->convertFromXaf($xafAmount);

        return $this->code === 'XAF'
            ? number_format($converted).' XAF'
            : $this->symbol.number_format($converted);
    }
}

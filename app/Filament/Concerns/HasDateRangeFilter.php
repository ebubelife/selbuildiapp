<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait HasDateRangeFilter
{
    protected static function dateRangeFilter(string $column, string $label): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                DatePicker::make('from')->native(false),
                DatePicker::make('until')->native(false),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                return $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate($column, '<=', $date));
            })
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = "{$label} from {$data['from']}";
                }

                if ($data['until'] ?? null) {
                    $indicators[] = "{$label} until {$data['until']}";
                }

                return $indicators;
            });
    }
}

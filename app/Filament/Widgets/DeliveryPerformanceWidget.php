<?php

namespace App\Filament\Widgets;

use App\Models\SupplierProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Basic delivery performance reporting per supplier (see #4 in the
 * stakeholder tracker) - average transit time and on-time rate, computed
 * directly from Shipment timestamps rather than raw SQL date-diff
 * functions, since those aren't portable between the app's MySQL database
 * and the SQLite test suite. Deliberately not an "average per day" chart
 * or anything fancier - "basic reporting" is what was actually asked for.
 */
class DeliveryPerformanceWidget extends TableWidget
{
    protected static ?string $heading = 'Delivery Performance by Supplier';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SupplierProfile::query()->whereHas('shipments', fn ($query) => $query->where('status', 'delivered'))
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('business_name')->label('Supplier'),
                TextColumn::make('delivered_count')
                    ->label('Delivered')
                    ->state(fn (SupplierProfile $record) => $record->shipments()->where('status', 'delivered')->count()),
                TextColumn::make('avg_transit')
                    ->label('Avg. Transit Time')
                    ->state(function (SupplierProfile $record) {
                        $delivered = $record->shipments()
                            ->where('status', 'delivered')
                            ->whereNotNull('dispatched_at')
                            ->whereNotNull('delivered_at')
                            ->get();

                        if ($delivered->isEmpty()) {
                            return '—';
                        }

                        $avgHours = $delivered->avg(fn ($shipment) => $shipment->dispatched_at->diffInHours($shipment->delivered_at));

                        return round($avgHours / 24, 1).' days';
                    }),
                TextColumn::make('on_time_rate')
                    ->label('On-Time Rate')
                    ->state(function (SupplierProfile $record) {
                        $withExpectedDate = $record->shipments()
                            ->where('status', 'delivered')
                            ->whereNotNull('expected_delivery_at')
                            ->get();

                        if ($withExpectedDate->isEmpty()) {
                            return '—';
                        }

                        $onTimeCount = $withExpectedDate->filter(fn ($shipment) => $shipment->isOnTime())->count();

                        return round($onTimeCount / $withExpectedDate->count() * 100).'%';
                    }),
            ]);
    }
}

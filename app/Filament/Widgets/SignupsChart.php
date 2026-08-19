<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SignupsChart extends ChartWidget
{
    protected ?string $heading = 'Signups by Role (Last 14 Days)';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => Carbon::today()->subDays($daysAgo));

        $roles = [
            'customer' => ['label' => 'Customers', 'color' => '#0A1B47'],
            'contractor' => ['label' => 'Contractors', 'color' => '#D99400'],
            'supplier' => ['label' => 'Suppliers', 'color' => '#606B87'],
        ];

        $datasets = [];

        foreach ($roles as $role => $meta) {
            $counts = $days->map(function (Carbon $day) use ($role) {
                return User::where('role', $role)
                    ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                    ->count();
            })->all();

            $datasets[] = [
                'label' => $meta['label'],
                'data' => $counts,
                'borderColor' => $meta['color'],
                'backgroundColor' => $meta['color'],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $days->map(fn (Carbon $day) => $day->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

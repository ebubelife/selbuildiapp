<?php

namespace App\Filament\Widgets;

use App\Models\PageView;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class VisitsChart extends ChartWidget
{
    protected ?string $heading = 'Site Visits (Last 14 Days)';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => Carbon::today()->subDays($daysAgo));

        $pageViews = [];
        $uniqueVisitors = [];

        foreach ($days as $day) {
            $query = PageView::whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
            $pageViews[] = (clone $query)->count();
            $uniqueVisitors[] = (clone $query)->distinct('session_id')->count('session_id');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Page Views',
                    'data' => $pageViews,
                    'borderColor' => '#D99400',
                    'backgroundColor' => 'rgba(217, 148, 0, 0.1)',
                ],
                [
                    'label' => 'Unique Visitors',
                    'data' => $uniqueVisitors,
                    'borderColor' => '#0A1B47',
                    'backgroundColor' => 'rgba(10, 27, 71, 0.1)',
                ],
            ],
            'labels' => $days->map(fn (Carbon $day) => $day->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

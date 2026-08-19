<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SignupStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $roleStat = function (string $role, string $label, Heroicon $icon): Stat {
            $todayStart = Carbon::today();
            $weekStart = Carbon::now()->startOfWeek();

            $total = User::where('role', $role)->count();
            $today = User::where('role', $role)->where('created_at', '>=', $todayStart)->count();
            $thisWeek = User::where('role', $role)->where('created_at', '>=', $weekStart)->count();

            return Stat::make($label, $total)
                ->description("+{$today} today \u{00B7} +{$thisWeek} this week")
                ->descriptionIcon($icon)
                ->color($today > 0 ? 'success' : 'gray');
        };

        $ordersTotal = Order::count();
        $ordersToday = Order::where('placed_at', '>=', Carbon::today())->count();
        $ordersThisWeek = Order::where('placed_at', '>=', Carbon::now()->startOfWeek())->count();

        return [
            $roleStat('customer', 'Customers', Heroicon::OutlinedUsers),
            $roleStat('contractor', 'Contractors', Heroicon::OutlinedIdentification),
            $roleStat('supplier', 'Suppliers', Heroicon::OutlinedBuildingStorefront),
            Stat::make('Orders', $ordersTotal)
                ->description("+{$ordersToday} today \u{00B7} +{$ordersThisWeek} this week")
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->color($ordersToday > 0 ? 'success' : 'gray'),
        ];
    }
}

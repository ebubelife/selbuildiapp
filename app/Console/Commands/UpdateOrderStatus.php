<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use App\Services\TrustScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatus extends Command
{
    protected $signature = 'orders:update-status {order : Order ID or order number} {status} {--note=}';

    protected $description = 'Advance an order to a new status, log the change, and notify the customer. Stand-in for the Phase 5 supplier/admin fulfillment UI.';

    public function handle(): int
    {
        $identifier = $this->argument('order');
        $status = $this->argument('status');

        if (! in_array($status, Order::STATUSES, true)) {
            $this->error("Invalid status \"{$status}\". Must be one of: ".implode(', ', Order::STATUSES));

            return self::FAILURE;
        }

        $order = is_numeric($identifier)
            ? Order::find($identifier)
            : Order::where('order_number', $identifier)->first();

        if (! $order) {
            $this->error("No order found matching \"{$identifier}\".");

            return self::FAILURE;
        }

        $note = (string) $this->option('note');

        DB::transaction(function () use ($order, $status, $note) {
            $order->update(['status' => $status]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $status,
                'note' => $note !== '' ? $note : null,
                'changed_by' => null,
            ]);
        });

        $order->user->notify(new OrderStatusUpdated($order, $note));

        if ($status === 'delivered') {
            app(TrustScoreService::class)->recordEvent($order->user, 'order_completed', $order);
        } elseif ($status === 'cancelled') {
            app(TrustScoreService::class)->recordEvent($order->user, 'cancellation', $order);
        }

        $this->info("Order {$order->order_number} is now \"{$order->statusLabel()}\". Customer notified.");

        return self::SUCCESS;
    }
}

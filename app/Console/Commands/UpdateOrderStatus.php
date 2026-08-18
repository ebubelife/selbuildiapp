<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderFulfillmentService;
use Illuminate\Console\Command;

class UpdateOrderStatus extends Command
{
    protected $signature = 'orders:update-status {order : Order ID or order number} {status} {--note=}';

    protected $description = 'Advance an order to a new status, log the change, and notify the customer. Stand-in for admin/ops overriding a multi-supplier order the supplier fulfillment UI can\'t cascade automatically.';

    public function handle(OrderFulfillmentService $fulfillmentService): int
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

        $fulfillmentService->advanceOrderStatus($order, $status, $note);

        $this->info("Order {$order->order_number} is now \"{$order->statusLabel()}\". Customer notified.");

        return self::SUCCESS;
    }
}

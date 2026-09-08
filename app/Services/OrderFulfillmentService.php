<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\SupplierOrderStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderFulfillmentService
{
    public function __construct(private TrustScoreService $trustScoreService)
    {
    }

    /**
     * Advance an order's overall status - the source of truth for the
     * customer-facing tracking timeline. Used directly by admin/ops (the
     * orders:update-status command) and indirectly by supplier fulfillment
     * actions on single-supplier orders (see advanceItemStatus()).
     */
    public function advanceOrderStatus(Order $order, string $status, ?string $note = null, ?User $changedBy = null): Order
    {
        DB::transaction(function () use ($order, $status, $note, $changedBy) {
            $order->update(['status' => $status]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $status,
                'note' => $note ?: null,
                'changed_by' => $changedBy?->id,
            ]);

            // An admin-forced order-level status change (the only option
            // today for a multi-supplier order - see class docblock on
            // advanceItemStatus()) applies to every supplier's shipment on
            // this order, since there's no per-supplier status input here.
            $order->shipments->each(fn (Shipment $shipment) => $this->syncShipmentStatus($shipment, $status));
        });

        // The status change itself already committed above - a mail
        // failure here must never look like the status update failed too.
        try {
            $order->user->notify(new OrderStatusUpdated($order, (string) $note));
        } catch (Throwable $e) {
            Log::error('OrderStatusUpdated notification failed to send', [
                'order_id' => $order->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notifySuppliers($order, (string) $note);

        if ($status === 'delivered') {
            $this->trustScoreService->recordEvent($order->user, 'order_completed', $order);
        } elseif ($status === 'cancelled') {
            $this->trustScoreService->recordEvent($order->user, 'cancellation', $order);
        }

        return $order->fresh();
    }

    /**
     * A supplier updates the fulfillment status of their own line item(s)
     * on an order. Orders can span multiple suppliers, and there's no
     * per-supplier shipment/status model yet (see PROJECT_PLAN.md §9 Phase
     * 4) - so this only cascades to the order-level status (and therefore
     * the customer's tracking page) when every item on the order belongs
     * to this same supplier. On a multi-supplier order, only the item's
     * own fulfillment_status changes; the order stays at whatever an
     * admin/ops last set it to via advanceOrderStatus().
     */
    public function advanceItemStatus(OrderItem $item, string $status, ?User $changedBy = null): OrderItem
    {
        $item->update(['fulfillment_status' => $status]);

        $shipment = Shipment::firstOrCreate(
            ['order_id' => $item->order_id, 'supplier_profile_id' => $item->supplier_profile_id],
        );
        $this->syncShipmentStatus($shipment, $status);

        $order = $item->order()->with('items')->first();
        $singleSupplierOrder = $order->items->pluck('supplier_profile_id')->unique()->count() === 1;

        if ($singleSupplierOrder && in_array($status, Order::STATUSES, true) && $order->status !== $status) {
            $this->advanceOrderStatus($order, $status, changedBy: $changedBy);
        }

        return $item->fresh();
    }

    /**
     * Mirrors a fulfillment status onto its shipment record, stamping
     * dispatched_at/delivered_at the first time each is reached. Never
     * overwrites a timestamp that's already set, so a later status change
     * (or the order-level and item-level paths both firing) can't clobber
     * the original dispatch/delivery time.
     */
    private function syncShipmentStatus(Shipment $shipment, string $status): void
    {
        $updates = ['status' => $status];

        if ($status === 'shipped' && ! $shipment->dispatched_at) {
            $updates['dispatched_at'] = now();
        }

        if ($status === 'delivered' && ! $shipment->delivered_at) {
            $updates['delivered_at'] = now();
        }

        $shipment->update($updates);
    }

    /**
     * Every supplier who has at least one item on this order gets notified
     * too, not just the buyer - they need to know when to start
     * fulfilling (confirmed) or stop (cancelled), independent of whichever
     * per-item fulfillment status they've set themselves.
     */
    private function notifySuppliers(Order $order, string $note): void
    {
        $order->load('items.supplierProfile.user');

        $order->items
            ->pluck('supplierProfile')
            ->filter()
            ->unique('id')
            ->each(function ($supplierProfile) use ($order, $note) {
                try {
                    $supplierProfile->user?->notify(new SupplierOrderStatusUpdated($order, $note));
                } catch (Throwable $e) {
                    Log::error('SupplierOrderStatusUpdated notification failed to send', [
                        'order_id' => $order->id,
                        'supplier_profile_id' => $supplierProfile->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}

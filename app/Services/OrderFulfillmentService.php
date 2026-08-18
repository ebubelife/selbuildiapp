<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\DB;

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
        });

        $order->user->notify(new OrderStatusUpdated($order, (string) $note));

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

        $order = $item->order()->with('items')->first();
        $singleSupplierOrder = $order->items->pluck('supplier_profile_id')->unique()->count() === 1;

        if ($singleSupplierOrder && in_array($status, Order::STATUSES, true) && $order->status !== $status) {
            $this->advanceOrderStatus($order, $status, changedBy: $changedBy);
        }

        return $item->fresh();
    }
}

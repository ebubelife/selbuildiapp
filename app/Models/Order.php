<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'user_id', 'project_id', 'status', 'subtotal', 'shipping_fee',
    'tax', 'discount', 'total', 'currency', 'exchange_rate', 'payment_status', 'payment_method',
    'shipping_address_id', 'placed_at',
])]
class Order extends Model
{
    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped',
        'out_for_delivery', 'delivered', 'cancelled', 'refunded',
    ];

    // Kept as a plain list here rather than reusing
    // PaymentGatewayManager::DRIVERS - that class lives in the
    // Services\Payments layer and pulls in the gateway manager just to
    // check a string, which isn't worth the coupling for what's really
    // just "which values does payment_method take that aren't
    // cash/credit."
    private const ONLINE_PAYMENT_METHODS = ['flutterwave', 'paystack', 'fapshi'];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Order items grouped by supplier, since each order can span multiple
     * suppliers who fulfill their portion independently.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, OrderItem>>
     */
    public function itemsBySupplier()
    {
        return $this->items->load('supplierProfile')->groupBy('supplier_profile_id');
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title();
    }

    public function isOnlinePayment(): bool
    {
        return in_array($this->payment_method, self::ONLINE_PAYMENT_METHODS, true);
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'selbuildi_credit' => 'Selbuildi Credit',
            'cash_on_delivery' => 'Cash / Pay on Delivery',
            'flutterwave' => 'Flutterwave',
            'paystack' => 'Paystack',
            'fapshi' => 'Fapshi (MTN/Orange Money)',
            default => ucfirst((string) $this->payment_method),
        };
    }

    /**
     * Whether this order is stuck waiting on an online payment that
     * either failed outright or never got past initialization - the
     * condition under which the order page offers a "Retry Payment"
     * button, since otherwise a customer whose card was declined (or who
     * closed the provider's page) had no way back in except placing a
     * brand new order.
     */
    public function canRetryPayment(): bool
    {
        return $this->isOnlinePayment()
            && $this->payment_status !== 'paid'
            && ! in_array($this->status, ['cancelled', 'refunded'], true);
    }

    /**
     * subtotal/shipping_fee/tax/discount/total are always stored in XAF -
     * the canonical ledger amount every existing report/sum assumes.
     * This converts that fixed XAF amount into whatever currency+rate
     * were snapshotted onto this order at placement time (or, for a
     * legacy/XAF order, is a no-op: exchange_rate defaults to 1).
     */
    public function chargedAmount(int $xafAmount): int
    {
        if ($this->currency === 'XAF' || (float) $this->exchange_rate <= 0) {
            return $xafAmount;
        }

        return (int) round($xafAmount / (float) $this->exchange_rate);
    }

    public function formattedTotal(): string
    {
        return number_format($this->chargedAmount($this->total)).' '.$this->currency;
    }

    public static function generateOrderNumber(): string
    {
        return 'SB-'.now()->format('ymd').'-'.strtoupper(str()->random(4));
    }
}

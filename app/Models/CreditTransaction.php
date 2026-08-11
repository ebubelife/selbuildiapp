<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['credit_account_id', 'order_id', 'type', 'amount', 'balance_after', 'due_date', 'paid_at', 'status'])]
class CreditTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The stored `status` isn't flipped to "overdue" by a scheduled job -
     * this derives it at read time from due_date instead, since nothing
     * else in the app runs on a schedule yet.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->due_date && $this->due_date->isPast()) {
            return 'overdue';
        }

        return $this->status;
    }
}

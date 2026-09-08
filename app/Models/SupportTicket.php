<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'type', 'product_id', 'subject', 'body', 'attachment_path',
    'status', 'assigned_to', 'response', 'responded_at',
])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'procurement_request' => "Can't Find What You Need",
            'quote_request' => 'Request a Quote',
            'complaint' => 'Complaint',
            'other' => 'Other',
            default => 'General Enquiry',
        };
    }
}

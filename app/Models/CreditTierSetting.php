<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single source of truth for what each Trust Score tier actually
 * gets - both the marketing copy on the homepage and CreditService's real
 * approval logic (auto-approve limit, Net-terms days) read from here, so
 * an admin can adjust either without a code deploy and the two can never
 * silently disagree with each other.
 *
 * deposit_percentage is captured for Silver's "30% deposit" perk but is
 * NOT currently enforced anywhere in checkout - there's no deposit-based
 * payment flow built yet, so this is display-only today.
 */
#[Fillable(['tier', 'perk_headline', 'auto_approve_limit', 'net_terms_days', 'deposit_percentage'])]
class CreditTierSetting extends Model
{
    public const ORDER = ['bronze', 'silver', 'gold', 'platinum'];

    protected function casts(): array
    {
        return [
            'auto_approve_limit' => 'integer',
            'net_terms_days' => 'integer',
            'deposit_percentage' => 'integer',
        ];
    }

    public function tierLabel(): string
    {
        return ucfirst($this->tier);
    }
}

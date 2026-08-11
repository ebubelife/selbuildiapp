<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'score', 'tier', 'calculated_at'])]
class ProcurementTrustScore extends Model
{
    public const TIERS = ['unrated', 'bronze', 'silver', 'gold', 'platinum'];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tierLabel(): string
    {
        return ucfirst($this->tier);
    }

    public static function tierForScore(int $score): string
    {
        return match (true) {
            $score >= 86 => 'platinum',
            $score >= 66 => 'gold',
            $score >= 41 => 'silver',
            $score >= 1 => 'bronze',
            default => 'unrated',
        };
    }
}

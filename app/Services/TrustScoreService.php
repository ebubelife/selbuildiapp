<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProcurementTrustScore;
use App\Models\TrustScoreEvent;
use App\Models\User;

class TrustScoreService
{
    /**
     * Points awarded/deducted per event type. The score is never edited
     * directly - it's always the clamped sum of these, recalculated from
     * trust_score_events, so it stays auditable.
     */
    public const POINTS = [
        'order_completed' => 4,
        'on_time_payment' => 3,
        'late_payment' => -5,
        'dispute' => -8,
        'cancellation' => -3,
        'kyc_verified' => 5,
    ];

    public function recordEvent(User $user, string $eventType, ?Order $order = null): TrustScoreEvent
    {
        $event = $user->trustScoreEvents()->create([
            'event_type' => $eventType,
            'points_delta' => self::POINTS[$eventType],
            'related_order_id' => $order?->id,
        ]);

        $this->recalculate($user);

        return $event;
    }

    public function recalculate(User $user): ProcurementTrustScore
    {
        $score = max(0, min(100, $user->trustScoreEvents()->sum('points_delta')));

        return ProcurementTrustScore::updateOrCreate(
            ['user_id' => $user->id],
            [
                'score' => $score,
                'tier' => ProcurementTrustScore::tierForScore($score),
                'calculated_at' => now(),
            ]
        );
    }

    public function currentTier(User $user): string
    {
        return $user->trustScore?->tier ?? 'unrated';
    }
}

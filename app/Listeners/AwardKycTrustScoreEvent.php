<?php

namespace App\Listeners;

use App\Services\TrustScoreService;
use Illuminate\Auth\Events\Verified;

class AwardKycTrustScoreEvent
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user->trustScoreEvents()->where('event_type', 'kyc_verified')->exists()) {
            return;
        }

        app(TrustScoreService::class)->recordEvent($user, 'kyc_verified');
    }
}

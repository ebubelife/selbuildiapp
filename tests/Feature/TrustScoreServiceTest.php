<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrustScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_an_event_creates_it_and_recalculates_the_score(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(TrustScoreService::class);

        $service->recordEvent($user, 'order_completed');

        $this->assertDatabaseHas('trust_score_events', [
            'user_id' => $user->id,
            'event_type' => 'order_completed',
            'points_delta' => 4,
        ]);

        $this->assertSame(4, $user->trustScore->score);
        $this->assertSame('bronze', $user->trustScore->tier);
    }

    public function test_score_is_clamped_at_zero(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(TrustScoreService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->recordEvent($user, 'dispute');
        }

        $this->assertSame(0, $user->trustScore->score);
        $this->assertSame('unrated', $user->trustScore->tier);
    }

    public function test_score_is_clamped_at_one_hundred(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $service = app(TrustScoreService::class);

        for ($i = 0; $i < 30; $i++) {
            $service->recordEvent($user, 'order_completed');
        }

        $this->assertSame(100, $user->trustScore->score);
        $this->assertSame('platinum', $user->trustScore->tier);
    }

    #[DataProvider('tierBoundaryProvider')]
    public function test_tier_boundaries(int $score, string $expectedTier): void
    {
        $this->assertSame($expectedTier, \App\Models\ProcurementTrustScore::tierForScore($score));
    }

    public static function tierBoundaryProvider(): array
    {
        return [
            'zero is unrated' => [0, 'unrated'],
            'one is bronze' => [1, 'bronze'],
            '40 is bronze' => [40, 'bronze'],
            '41 is silver' => [41, 'silver'],
            '65 is silver' => [65, 'silver'],
            '66 is gold' => [66, 'gold'],
            '85 is gold' => [85, 'gold'],
            '86 is platinum' => [86, 'platinum'],
            '100 is platinum' => [100, 'platinum'],
        ];
    }

    public function test_currentTier_defaults_to_unrated_when_no_score_row_exists(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->assertSame('unrated', app(TrustScoreService::class)->currentTier($user));
    }
}

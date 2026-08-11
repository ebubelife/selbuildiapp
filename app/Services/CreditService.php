<?php

namespace App\Services;

use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Only Gold/Platinum unlock real Net-15/Net-30 credit per the tier
     * table in PROJECT_PLAN.md §5. Bronze is "eligible to apply" but
     * always routes to manual review; Silver's perk is a 30%-deposit
     * checkout flow, not a credit line, so it isn't auto-approved here
     * either - a Silver application still has to go through review.
     */
    public const AUTO_APPROVE_LIMITS = [
        'gold' => 200000,
        'platinum' => 500000,
    ];

    public const NET_TERMS_DAYS = [
        'gold' => 15,
        'platinum' => 30,
    ];

    public function __construct(private TrustScoreService $trustScoreService)
    {
    }

    public function canApply(User $user): bool
    {
        return $this->trustScoreService->currentTier($user) !== 'unrated';
    }

    public function applyForCredit(User $user, int $requestedLimit): CreditAccount
    {
        $tier = $this->trustScoreService->currentTier($user);
        $autoLimit = self::AUTO_APPROVE_LIMITS[$tier] ?? 0;

        $approved = $autoLimit > 0 && $requestedLimit <= $autoLimit;

        return CreditAccount::updateOrCreate(
            ['user_id' => $user->id],
            $approved
                ? [
                    'credit_limit' => $requestedLimit,
                    'available_credit' => $requestedLimit,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'reviewed_by' => null,
                ]
                : [
                    'credit_limit' => $requestedLimit,
                    'available_credit' => 0,
                    'status' => 'pending',
                    'approved_at' => null,
                    'reviewed_by' => null,
                ]
        );
    }

    public function review(CreditAccount $account, bool $approve, ?int $limit, ?User $reviewer): CreditAccount
    {
        if ($approve) {
            $account->update([
                'credit_limit' => $limit ?? $account->credit_limit,
                'available_credit' => $limit ?? $account->credit_limit,
                'status' => 'approved',
                'approved_at' => now(),
                'reviewed_by' => $reviewer?->id,
            ]);
        } else {
            $account->update([
                'status' => 'rejected',
                'available_credit' => 0,
                'reviewed_by' => $reviewer?->id,
            ]);
        }

        return $account->fresh();
    }

    public function drawdown(CreditAccount $account, Order $order): CreditTransaction
    {
        $tier = $this->trustScoreService->currentTier($account->user);
        $termsDays = self::NET_TERMS_DAYS[$tier] ?? 15;

        return DB::transaction(function () use ($account, $order, $termsDays) {
            $account->decrement('available_credit', $order->total);

            return $account->transactions()->create([
                'order_id' => $order->id,
                'type' => 'drawdown',
                'amount' => $order->total,
                'balance_after' => $account->fresh()->available_credit,
                'due_date' => now()->addDays($termsDays)->toDateString(),
                'status' => 'pending',
            ]);
        });
    }

    public function repay(CreditTransaction $drawdown): CreditTransaction
    {
        $account = $drawdown->creditAccount;
        $onTime = ! $drawdown->due_date || ! $drawdown->due_date->isPast();

        return DB::transaction(function () use ($account, $drawdown, $onTime) {
            $account->increment('available_credit', $drawdown->amount);

            $repayment = $account->transactions()->create([
                'order_id' => $drawdown->order_id,
                'type' => 'repayment',
                'amount' => $drawdown->amount,
                'balance_after' => $account->fresh()->available_credit,
                'paid_at' => now(),
                'status' => 'paid',
            ]);

            $drawdown->update(['status' => 'paid', 'paid_at' => now()]);

            $this->trustScoreService->recordEvent(
                $account->user,
                $onTime ? 'on_time_payment' : 'late_payment',
                $drawdown->order
            );

            return $repayment;
        });
    }
}

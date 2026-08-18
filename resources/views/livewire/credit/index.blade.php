<?php

use App\Models\ProcurementTrustScore;
use App\Services\CreditService;
use App\Services\TrustScoreService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    public int $requestedLimit = 50000;

    public function mount(): void
    {
        abort_if(Auth::user()->isSupplier(), 403);
    }

    public function applyForCredit(CreditService $creditService): void
    {
        $this->validate([
            'requestedLimit' => ['required', 'integer', 'min:10000', 'max:2000000'],
        ]);

        $creditService->applyForCredit(Auth::user(), $this->requestedLimit);
    }

    public function repay(int $transactionId, CreditService $creditService): void
    {
        $account = Auth::user()->creditAccount;

        abort_unless($account, 404);

        $transaction = $account->transactions()
            ->where('id', $transactionId)
            ->where('type', 'drawdown')
            ->whereNot('status', 'paid')
            ->firstOrFail();

        $creditService->repay($transaction);
    }

    public function with(TrustScoreService $trustScoreService): array
    {
        $user = Auth::user()->load('creditAccount.transactions.order', 'trustScoreEvents');

        $score = $user->trustScore?->score ?? 0;
        $tier = $trustScoreService->currentTier($user);

        $tiers = ProcurementTrustScore::TIERS;
        $tierIndex = array_search($tier, $tiers);
        $nextTier = $tiers[$tierIndex + 1] ?? null;
        $nextTierThreshold = match ($nextTier) {
            'bronze' => 1, 'silver' => 41, 'gold' => 66, 'platinum' => 86, default => null,
        };

        return [
            'score' => $score,
            'tier' => $tier,
            'nextTier' => $nextTier,
            'nextTierThreshold' => $nextTierThreshold,
            'creditAccount' => $user->creditAccount,
            'canApply' => app(CreditService::class)->canApply($user),
            'events' => $user->trustScoreEvents()->latest()->limit(10)->get(),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">My Credit &amp; Trust</h1>
            <p class="mt-1 text-navy-200 text-sm">Your Procurement Trust Score and Selbuildi Credit standing.</p>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-8 items-center bg-white rounded-2xl border border-navy-100 p-8">
                <div class="flex justify-center">
                    <div class="relative w-48 h-48" wire:key="gauge-{{ $score }}">
                        <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#F1F4FA" stroke-width="12" />
                            <circle
                                x-data="{ offset: 327 }"
                                x-init="setTimeout(() => offset = 327 - (327 * ({{ $score }} / 100)), 200)"
                                cx="60" cy="60" r="52" fill="none" stroke="#D99400" stroke-width="12"
                                stroke-linecap="round"
                                stroke-dasharray="327"
                                :stroke-dashoffset="offset"
                                style="transition: stroke-dashoffset 1.4s ease-out"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="font-heading text-4xl font-bold text-navy-900">{{ $score }}</span>
                            <span class="text-xs text-gold-800 font-semibold uppercase tracking-wide mt-1">{{ ucfirst($tier) }} Tier</span>
                        </div>
                    </div>
                </div>

                <div>
                    <span class="text-sm font-semibold text-gold-800 uppercase tracking-wide">Procurement Trust Score</span>
                    <h2 class="mt-2 font-heading text-xl font-bold text-navy-900">
                        @if ($nextTier)
                            {{ $nextTierThreshold - $score }} {{ Str::plural('point', $nextTierThreshold - $score) }} to {{ ucfirst($nextTier) }}
                        @else
                            You've reached the top tier
                        @endif
                    </h2>
                    <p class="mt-2 text-sm text-navy-500 leading-relaxed">
                        Completing orders on time builds your score. Cancellations and disputes lower it. Higher tiers unlock better payment terms on Selbuildi Credit.
                    </p>

                    <div class="mt-6 grid grid-cols-4 gap-2">
                        @foreach (['bronze' => 1, 'silver' => 41, 'gold' => 66, 'platinum' => 86] as $t => $threshold)
                            <div @class([
                                'rounded-lg border p-2 text-center',
                                'border-gold-400 bg-gold-50' => $tier === $t,
                                'border-navy-100' => $tier !== $t,
                            ])>
                                <p class="text-[11px] font-semibold text-navy-700">{{ ucfirst($t) }}</p>
                                <p class="text-[10px] text-navy-400">{{ $threshold }}+</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Credit account -->
            <div class="mt-6 bg-white rounded-2xl border border-navy-100 p-6">
                <h2 class="font-heading font-bold text-lg text-navy-900">Selbuildi Credit</h2>

                @if (! $creditAccount || $creditAccount->status === 'none' || $creditAccount->status === 'rejected')
                    @if ($creditAccount?->status === 'rejected')
                        <p class="mt-2 text-sm text-red-600">Your last application wasn't approved. You can apply again below.</p>
                    @endif

                    @if ($canApply)
                        <form wire:submit="applyForCredit" class="mt-4 flex flex-col sm:flex-row items-start sm:items-end gap-4">
                            <div class="w-full sm:w-64">
                                <x-input-label for="requestedLimit" value="Requested limit (XAF)" />
                                <x-text-input wire:model="requestedLimit" id="requestedLimit" type="number" min="10000" step="1000" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('requestedLimit')" class="mt-1" />
                            </div>
                            <x-primary-button type="submit">Apply for Credit</x-primary-button>
                        </form>
                        <p class="mt-3 text-xs text-navy-400">Gold and Platinum tier requests within the tier limit are approved instantly. Other requests go to manual review.</p>
                    @else
                        <p class="mt-2 text-sm text-navy-500">Complete your first order to become eligible to apply for Selbuildi Credit.</p>
                    @endif
                @elseif ($creditAccount->status === 'pending')
                    <div class="mt-4 p-4 rounded-xl bg-gold-50 border border-gold-100">
                        <p class="text-sm font-semibold text-navy-900">Application under review</p>
                        <p class="mt-1 text-sm text-navy-600">You requested a {{ number_format($creditAccount->credit_limit) }} XAF limit. We'll email you once it's reviewed.</p>
                    </div>
                @elseif ($creditAccount->status === 'suspended')
                    <div class="mt-4 p-4 rounded-xl bg-red-50 border border-red-100">
                        <p class="text-sm font-semibold text-red-700">Credit suspended</p>
                        <p class="mt-1 text-sm text-red-600">Your Selbuildi Credit is currently suspended. Contact support to resolve outstanding balances.</p>
                    </div>
                @else
                    <div class="mt-4 grid sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-navy-50 border border-navy-100">
                            <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide">Available Credit</p>
                            <p class="mt-1 font-heading font-bold text-xl text-navy-900">{{ number_format($creditAccount->available_credit) }} XAF</p>
                        </div>
                        <div class="p-4 rounded-xl bg-navy-50 border border-navy-100">
                            <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide">Credit Limit</p>
                            <p class="mt-1 font-heading font-bold text-xl text-navy-900">{{ number_format($creditAccount->credit_limit) }} XAF</p>
                        </div>
                    </div>

                    @php $drawdowns = $creditAccount->transactions->where('type', 'drawdown')->whereNotIn('status', ['paid'])->sortBy('due_date'); @endphp
                    @if ($drawdowns->isNotEmpty())
                        <div class="mt-6">
                            <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide mb-2">Outstanding</p>
                            <ul class="divide-y divide-navy-100 border border-navy-100 rounded-xl overflow-hidden">
                                @foreach ($drawdowns as $txn)
                                    <li class="flex items-center justify-between gap-4 p-4" wire:key="txn-{{ $txn->id }}">
                                        <div>
                                            <p class="font-semibold text-navy-900 text-sm">{{ number_format($txn->amount) }} XAF</p>
                                            <p @class([
                                                'text-xs mt-0.5',
                                                'text-red-600 font-semibold' => $txn->effectiveStatus() === 'overdue',
                                                'text-navy-400' => $txn->effectiveStatus() !== 'overdue',
                                            ])>
                                                {{ $txn->effectiveStatus() === 'overdue' ? 'Overdue since' : 'Due' }} {{ $txn->due_date?->format('M j, Y') }}
                                            </p>
                                        </div>
                                        <x-secondary-button wire:click="repay({{ $txn->id }})" wire:loading.attr="disabled">
                                            Repay
                                        </x-secondary-button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            @if ($events->isNotEmpty())
                <div class="mt-6 bg-white rounded-2xl border border-navy-100 p-6">
                    <h2 class="font-heading font-bold text-lg text-navy-900">Recent Trust Activity</h2>
                    <ul class="mt-4 divide-y divide-navy-100">
                        @foreach ($events as $event)
                            <li class="flex items-center justify-between gap-4 py-3">
                                <p class="text-sm text-navy-600">{{ str($event->event_type)->replace('_', ' ')->ucfirst() }}</p>
                                <span @class([
                                    'text-sm font-semibold',
                                    'text-green-600' => $event->points_delta > 0,
                                    'text-red-600' => $event->points_delta < 0,
                                ])>
                                    {{ $event->points_delta > 0 ? '+' : '' }}{{ $event->points_delta }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
</div>

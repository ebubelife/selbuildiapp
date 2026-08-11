<?php

namespace App\Console\Commands;

use App\Models\CreditAccount;
use App\Services\CreditService;
use Illuminate\Console\Command;

class ReviewCreditApplication extends Command
{
    protected $signature = 'credit:review {user : User ID or email} {decision : approve|reject} {--limit= : Approved credit limit in XAF (required for approve)}';

    protected $description = 'Approve or reject a pending Selbuildi Credit application. Stand-in for the Phase 5 admin panel\'s credit review queue.';

    public function handle(CreditService $creditService): int
    {
        $identifier = $this->argument('user');
        $decision = $this->argument('decision');

        if (! in_array($decision, ['approve', 'reject'], true)) {
            $this->error('Decision must be "approve" or "reject".');

            return self::FAILURE;
        }

        $account = is_numeric($identifier)
            ? CreditAccount::where('user_id', $identifier)->first()
            : CreditAccount::whereHas('user', fn ($q) => $q->where('email', $identifier))->first();

        if (! $account) {
            $this->error("No credit account found for \"{$identifier}\".");

            return self::FAILURE;
        }

        if ($account->status !== 'pending') {
            $this->error("This account's status is \"{$account->status}\", not pending review.");

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($decision === 'approve' && $limit === null) {
            $this->error('--limit is required to approve.');

            return self::FAILURE;
        }

        $creditService->review($account, $decision === 'approve', $limit, null);

        $this->info("Credit application for {$account->user->email} ".($decision === 'approve' ? "approved with a {$limit} XAF limit." : 'rejected.'));

        return self::SUCCESS;
    }
}

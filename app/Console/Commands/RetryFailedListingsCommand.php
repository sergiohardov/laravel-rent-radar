<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\Pipeline;
use Illuminate\Console\Command;

class RetryFailedListingsCommand extends Command
{
    protected $signature = 'listings:retry-failed {--hours=24 : Only listings saved within this many hours}';

    protected $description = 'Run failed listings through the pipeline again, oldest first, within the run time budget';

    public function handle(Pipeline $pipeline): int
    {
        $startedAt = microtime(true);
        $budget = (float) config('sources.time_budget_seconds');
        $counts = array_fill_keys(array_column(Decision::cases(), 'value'), 0);

        $listings = Listing::query()
            ->where('decision', Decision::Failed)
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            // by id, so a listing that leaves "failed" mid-run does not shift the pages
            ->lazyById(20);

        foreach ($listings as $listing) {
            if (microtime(true) - $startedAt >= $budget) {
                break;
            }

            $counts[$pipeline->reprocess($listing)->value]++;
        }

        $this->info(sprintf(
            'retried %d: passed %d, rejected %d, failed %d',
            array_sum($counts),
            $counts[Decision::Pending->value],
            $counts[Decision::Rejected->value],
            $counts[Decision::Failed->value],
        ));

        return self::SUCCESS;
    }
}

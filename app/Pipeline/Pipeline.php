<?php

namespace App\Pipeline;

use App\Models\Listing;
use App\Sources\ListingSource;

/**
 * Pulls listings from a source one by one and runs each new one through the stages.
 * Cheap stages go first; the first rejecting stage ends processing of that listing.
 */
final class Pipeline
{
    /**
     * @param  list<Stage>  $stages
     */
    public function __construct(
        private readonly SeenFilter $seenFilter,
        private readonly array $stages,
        private readonly int $stopAfterSeen,
        private readonly float $timeBudgetSeconds,
    ) {}

    public function run(ListingSource $source): RunReport
    {
        $startedAt = microtime(true);
        $fetched = $seen = $created = $passed = $rejected = $seenInRow = 0;
        $stopReason = StopReason::Exhausted;

        foreach ($source->fetch() as $raw) {
            if (microtime(true) - $startedAt >= $this->timeBudgetSeconds) {
                $stopReason = StopReason::TimeBudget;
                break;
            }

            $fetched++;

            if ($this->seenFilter->isSeen($raw)) {
                $seen++;
                if (++$seenInRow >= $this->stopAfterSeen) {
                    $stopReason = StopReason::SeenInRow;
                    break;
                }

                continue;
            }

            $seenInRow = 0;
            $created++;
            $this->process(Listing::query()->create($raw->toListingAttributes())) ? $passed++ : $rejected++;
        }

        return new RunReport($fetched, $seen, $created, $passed, $rejected, $stopReason);
    }

    private function process(Listing $listing): bool
    {
        foreach ($this->stages as $stage) {
            $result = $stage->handle($listing);

            if (! $result->passed) {
                $listing->fill([
                    'decision' => Decision::Rejected,
                    'rejected_by' => $stage->name(),
                    'reject_reason' => $result->reason,
                ])->save();

                return false;
            }
        }

        $listing->save();

        return true;
    }
}

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
        $fetched = $seen = $created = $passed = $rejected = $failed = $seenInRow = 0;
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
            match ($this->process(Listing::query()->create($raw->toListingAttributes()))) {
                Decision::Pending => $passed++,
                Decision::Failed => $failed++,
                default => $rejected++,
            };
        }

        return new RunReport(
            $fetched, $seen, $created, $passed, $rejected, $stopReason,
            failed: $failed,
            seconds: round(microtime(true) - $startedAt, 1),
            // Whole process peak, to compare with the hosting's 128M memory_limit
            peakMemoryMb: (int) ceil(memory_get_peak_usage(true) / 1024 / 1024),
        );
    }

    /**
     * Runs a saved listing through all stages again, e.g. after the LLM was down.
     */
    public function reprocess(Listing $listing): Decision
    {
        $listing->fill(['decision' => Decision::Pending, 'rejected_by' => null, 'reject_reason' => null]);

        return $this->process($listing);
    }

    /**
     * @return Decision the listing's decision after the stages
     */
    private function process(Listing $listing): Decision
    {
        foreach ($this->stages as $stage) {
            $result = $stage->handle($listing);

            if (! $result->passed) {
                $listing->fill([
                    'decision' => $result->failed ? Decision::Failed : Decision::Rejected,
                    'rejected_by' => $stage->name(),
                    'reject_reason' => $result->reason,
                ])->save();

                return $listing->decision;
            }
        }

        $listing->save();

        return $listing->decision;
    }
}

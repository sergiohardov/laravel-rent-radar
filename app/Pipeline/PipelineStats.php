<?php

namespace App\Pipeline;

use App\Models\Listing;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Numbers for the admin dashboard, read from the listings journal.
 */
final class PipelineStats
{
    /**
     * How many of the listings found since then got past each step.
     *
     * @return array{found: int, passed_fields: int, enriched: int, in_sector: int, sent: int}
     */
    public function funnel(CarbonInterface $since): array
    {
        $found = fn () => Listing::query()->where('created_at', '>=', $since);

        return [
            'found' => $found()->count(),
            'passed_fields' => $found()->where(fn ($q) => $q->whereNull('rejected_by')->orWhere('rejected_by', '!=', 'hard_filter'))->count(),
            'enriched' => $found()->whereNotNull('enriched_at')->count(),
            // Pending at rest has passed every stage and waits in the card queue
            'in_sector' => $found()->whereIn('decision', [Decision::Pending, Decision::Notified])->count(),
            'sent' => $found()->where('decision', Decision::Notified)->count(),
        ];
    }

    /**
     * Rejected listings since then by reason group, plus LLM failures and the rest.
     *
     * @return array<string, int> label => count, most frequent first, zeros left out
     */
    public function rejectReasons(CarbonInterface $since): array
    {
        $rejected = fn () => Listing::query()->where('created_at', '>=', $since)->where('decision', Decision::Rejected);

        $counts = [];
        foreach (RejectReasons::labels() as $group => $label) {
            $counts[$label] = RejectReasons::apply($rejected(), $group)->count();
        }
        $counts['Другое'] = $rejected()->count() - array_sum($counts);
        $counts['Сбой LLM'] = Listing::query()->where('created_at', '>=', $since)->where('decision', Decision::Failed)->count();

        arsort($counts);

        return array_filter($counts);
    }

    /**
     * LLM calls since then; a retried listing counts once.
     */
    public function llmCalls(CarbonInterface $since): int
    {
        return Listing::query()->where('enriched_at', '>=', $since)->count();
    }

    /**
     * Median minutes from the site's publishing time to the moment the bot
     * saved the listing, over listings published since then. Older ones are
     * left out: a first run picks up a day of them, which says nothing about speed.
     */
    public function medianDetectionMinutes(CarbonInterface $since): ?float
    {
        return $this->medianMinutes(
            Listing::query()
                ->where('published_at', '>=', $since)
                ->get(['published_at', 'created_at'])
                ->map(fn (Listing $listing) => $listing->published_at->diffInSeconds($listing->created_at, false)),
        );
    }

    /**
     * Median minutes from publishing to the card in the group, over cards sent since then.
     */
    public function medianCardMinutes(CarbonInterface $since): ?float
    {
        return $this->medianMinutes(
            Listing::query()
                ->where('notified_at', '>=', $since)
                ->whereNotNull('published_at')
                ->get(['published_at', 'notified_at'])
                ->map(fn (Listing $listing) => $listing->published_at->diffInSeconds($listing->notified_at, false)),
        );
    }

    /**
     * @param  Collection<int, float|int>  $seconds
     */
    private function medianMinutes(Collection $seconds): ?float
    {
        // A publishing time ahead of saving is a site's clock or time zone error
        $seconds = $seconds->filter(fn ($value) => $value >= 0)->values();
        if ($seconds->isEmpty()) {
            return null;
        }

        return round((float) $seconds->median() / 60, 1);
    }
}

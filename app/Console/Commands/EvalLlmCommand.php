<?php

namespace App\Console\Commands;

use Anthropic\Client;
use App\Enrichment\AnthropicListingEnricher;
use App\Location\LocationKind;
use App\Location\LocationMatcher;
use App\Location\OsmLocationMapper;
use App\Models\Listing;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs labelled real listings through the real Claude API and reports how
 * often each field matches, and what a listing costs. Spends real money.
 */
class EvalLlmCommand extends Command
{
    protected $signature = 'llm:eval
        {--model= : Model to evaluate; defaults to ANTHROPIC_MODEL}
        {--cases=tests/Fixtures/llm-eval/cases.json : Labelled listings}
        {--whitelist=tests/Fixtures/osm/overpass-kotovskoho-1km.json : Overpass answer for the sector whitelist}';

    protected $description = 'Check LLM extraction quality and cost on labelled real listings (calls the real API)';

    /** USD per million tokens: input, output; cache reads cost 0.1 and writes 1.25 of input */
    private const PRICES = [
        'claude-haiku-4-5' => [1.0, 5.0],
        'claude-sonnet-5-5' => [2.0, 10.0],
        'claude-opus-5-5' => [4.0, 20.0],
    ];

    public function handle(Client $client, OsmLocationMapper $mapper): int
    {
        $model = (string) ($this->option('model') ?: config('services.anthropic.model'));
        $cases = json_decode(file_get_contents(base_path($this->option('cases'))), true);
        $enricher = new AnthropicListingEnricher($client, $model);

        $hits = $totals = [];
        $tokens = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0];

        // The whitelist is replaced for the run and restored by the rollback
        DB::beginTransaction();

        try {
            $this->loadWhitelist($mapper);
            $matcher = new LocationMatcher;

            foreach ($cases as $case) {
                // Not a factory: Faker is a dev dependency, and this runs on the hosting too
                $listing = new Listing($case['listing']);
                $listing->enrichment = $enricher->enrich($listing)->toArray();
                $sector = $this->sector($matcher, $listing);

                $usage = $enricher->lastUsage;
                $tokens['input'] += $usage->inputTokens;
                $tokens['output'] += $usage->outputTokens;
                $tokens['cache_read'] += $usage->cacheReadInputTokens ?? 0;
                $tokens['cache_write'] += $usage->cacheCreationInputTokens ?? 0;

                $misses = [];
                foreach ($case['expect'] as $field => $expected) {
                    $actual = $field === 'sector' ? $sector : ($listing->enrichment[$field] ?? null);
                    $totals[$field] = ($totals[$field] ?? 0) + 1;

                    if ($actual === $expected) {
                        $hits[$field] = ($hits[$field] ?? 0) + 1;
                    } else {
                        $misses[] = "{$field}: expected ".json_encode($expected).', got '.json_encode($actual, JSON_UNESCAPED_UNICODE);
                    }
                }

                $this->line(($misses === [] ? '  ok  ' : '  MISS').' '.$case['id'].($misses === [] ? '' : ' — '.implode('; ', $misses)));
            }
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->table(['field', 'correct', 'accuracy'], collect($totals)->map(fn (int $total, string $field) => [
            $field,
            ($hits[$field] ?? 0)."/{$total}",
            round(100 * ($hits[$field] ?? 0) / $total).'%',
        ])->values()->all());

        $this->info($this->costLine($model, $tokens, count($cases)));

        return self::SUCCESS;
    }

    private function loadWhitelist(OsmLocationMapper $mapper): void
    {
        Location::query()->delete();

        $overpass = json_decode(file_get_contents(base_path($this->option('whitelist'))), true);
        foreach ($mapper->map($overpass['elements']) as $entry) {
            Location::query()->create($entry);
        }
    }

    /**
     * "in" for a street or complex, "district" for a district only, "out" otherwise.
     */
    private function sector(LocationMatcher $matcher, Listing $listing): string
    {
        if (! $matcher->handle($listing)->passed) {
            return 'out';
        }

        return ($listing->enrichment['sector_match']['kind'] ?? null) === LocationKind::District->value ? 'district' : 'in';
    }

    /**
     * @param  array{input: int, output: int, cache_read: int, cache_write: int}  $tokens
     */
    private function costLine(string $model, array $tokens, int $listings): string
    {
        $summary = sprintf(
            '%s: %d listings, tokens in %d, out %d, cache read %d, cache write %d',
            $model, $listings, $tokens['input'], $tokens['output'], $tokens['cache_read'], $tokens['cache_write'],
        );

        if (! isset(self::PRICES[$model])) {
            return $summary.'; no price for this model';
        }

        [$in, $out] = self::PRICES[$model];
        $usd = ($tokens['input'] * $in + $tokens['output'] * $out
            + $tokens['cache_read'] * $in * 0.1 + $tokens['cache_write'] * $in * 1.25) / 1_000_000;

        return sprintf('%s; $%.4f in total, $%.4f per listing', $summary, $usd, $usd / max($listings, 1));
    }
}

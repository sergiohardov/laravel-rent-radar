<?php

namespace App\Pipeline\Stages;

use App\Currency\CurrencyCode;
use App\Enrichment\Enrichment;
use App\Models\Listing;
use App\Models\Setting;
use App\Pipeline\Flag;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;

/**
 * The hard filter again, after the LLM read the text:
 * - fields the site left empty are taken from the text; the site's own values stay;
 * - where the text is silent about outages, the site's own fields fill in;
 * - an explicit "no generator" rejects, an unknown generator passes with ⚡❓;
 * - then rooms, floor and price are checked again.
 */
final class TextHardFilter implements Stage
{
    private ?Setting $settings = null;

    public function __construct(
        private readonly HardFilter $hardFilter,
    ) {}

    public function name(): string
    {
        return 'hard_filter_text';
    }

    public function handle(Listing $listing): StageResult
    {
        $settings = $this->settings ??= Setting::current();
        $enrichment = Enrichment::fromArray($listing->enrichment ?? []);

        $this->fillFromText($listing, $enrichment);
        $listing->enrichment = $this->withSourceHints($enrichment->toArray(), $listing->source_hints ?? []);

        return $this->checkGenerator($listing, $listing->enrichment['generator'], $settings->allow_unknown_generator)
            ?? $this->hardFilter->handle($listing);
    }

    private function fillFromText(Listing $listing, Enrichment $text): void
    {
        $listing->rooms ??= $text->rooms;
        $listing->floor ??= $text->floor;
        $listing->floors_total ??= $text->floorsTotal;
        $listing->area ??= $text->area;

        if ($listing->price === null && $text->price !== null) {
            $listing->price = $text->price;
            $listing->currency = $text->currency ?? CurrencyCode::Uah;
        }
    }

    /**
     * @param  array<string, mixed>  $enrichment
     * @param  array<string, bool>  $hints
     * @return array<string, mixed>
     */
    private function withSourceHints(array $enrichment, array $hints): array
    {
        foreach ($hints as $field => $value) {
            if (array_key_exists($field, $enrichment) && $enrichment[$field] === null) {
                $enrichment[$field] = $value;
            }
        }

        return $enrichment;
    }

    private function checkGenerator(Listing $listing, ?bool $generator, bool $allowUnknown): ?StageResult
    {
        $listing->removeFlag(Flag::UnknownGenerator);

        if ($generator === false) {
            return StageResult::reject('no generator');
        }

        if ($generator === null) {
            if (! $allowUnknown) {
                return StageResult::reject('generator unknown');
            }

            $listing->addFlag(Flag::UnknownGenerator);
        }

        return null;
    }
}

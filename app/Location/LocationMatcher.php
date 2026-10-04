<?php

namespace App\Location;

use App\Models\Listing;
use App\Models\Location;
use App\Pipeline\Flag;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;
use Illuminate\Support\Collection;

/**
 * Is the listing in the sector? The LLM picked a whitelist entry from the text
 * (location_id); this checks that the entry exists. A street or complex puts the
 * listing in the sector, a district alone passes it with 📍?. An empty
 * whitelist means the sector is not set up yet, and every listing passes.
 */
final class LocationMatcher implements Stage
{
    /** @var Collection<int, Location>|null */
    private ?Collection $whitelist = null;

    public function name(): string
    {
        return 'location_matcher';
    }

    public function handle(Listing $listing): StageResult
    {
        $this->whitelist ??= Location::query()->get()->keyBy('id');
        $listing->removeFlag(Flag::DistrictOnly);

        if ($this->whitelist->isEmpty()) {
            return StageResult::pass();
        }

        $enrichment = $listing->enrichment ?? [];
        $location = $this->whitelist->get($enrichment['location_id'] ?? null);

        if ($location === null) {
            return StageResult::reject($this->notInSector($enrichment));
        }

        $enrichment['sector_match'] = ['id' => $location->id, 'kind' => $location->kind->value, 'name' => $location->name];
        $listing->enrichment = $enrichment;

        if ($location->kind === LocationKind::District) {
            $listing->addFlag(Flag::DistrictOnly);
        }

        return StageResult::pass();
    }

    /**
     * "not in sector: Бреуса · Малиновский" — what the LLM read, for the journal.
     *
     * @param  array<string, mixed>  $enrichment
     */
    private function notInSector(array $enrichment): string
    {
        $place = collect([$enrichment['complex'] ?? null, $enrichment['street'] ?? null, $enrichment['district'] ?? null])
            ->filter()
            ->implode(' · ');

        $unknownId = isset($enrichment['location_id']) ? " (unknown location_id {$enrichment['location_id']})" : '';

        return 'not in sector: '.($place !== '' ? $place : 'no place named').$unknownId;
    }
}

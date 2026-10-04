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
 * (location_id); this checks that the entry exists and, for a numbered entry
 * such as "54-я Жемчужина", that its number is in the listing text: the model
 * mixes up numbered complexes of one series. A street or complex puts the
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
            $unknownId = isset($enrichment['location_id']) ? "unknown location_id {$enrichment['location_id']}" : null;

            return StageResult::reject($this->notInSector($enrichment, $unknownId));
        }

        $missing = $this->numbersMissingFromText($location, $listing);
        if ($missing !== []) {
            return StageResult::reject($this->notInSector($enrichment, "picked {$location->name}, but ".implode(', ', $missing).' is not in the text'));
        }

        $enrichment['sector_match'] = ['id' => $location->id, 'kind' => $location->kind->value, 'name' => $location->name];
        $listing->enrichment = $enrichment;

        if ($location->kind === LocationKind::District) {
            $listing->addFlag(Flag::DistrictOnly);
        }

        return StageResult::pass();
    }

    /**
     * Numbers in the entry's name that the listing text does not contain as a number.
     *
     * @return list<string>
     */
    private function numbersMissingFromText(Location $location, Listing $listing): array
    {
        preg_match_all('/\d+/', $location->name, $numbers);
        if ($numbers[0] === []) {
            return [];
        }

        preg_match_all('/\d+/', implode(' ', [$listing->title, $listing->address, $listing->description]), $inText);

        return array_values(array_diff($numbers[0], $inText[0]));
    }

    /**
     * "not in sector: Бреуса · Малиновский" — what the LLM read, for the journal.
     *
     * @param  array<string, mixed>  $enrichment
     */
    private function notInSector(array $enrichment, ?string $why = null): string
    {
        $place = collect([$enrichment['complex'] ?? null, $enrichment['street'] ?? null, $enrichment['district'] ?? null])
            ->filter()
            ->implode(' · ');

        return 'not in sector: '.($place !== '' ? $place : 'no place named').($why !== null ? " ({$why})" : '');
    }
}

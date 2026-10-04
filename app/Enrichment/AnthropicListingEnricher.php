<?php

namespace App\Enrichment;

use Anthropic\Client;
use Anthropic\Messages\Message;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\Usage;
use App\Models\Listing;
use App\Models\Location;

/**
 * Claude reads the listing text and answers through one strict tool call.
 * The system prompt carries the sector whitelist and is cached; only the
 * listing itself changes between requests.
 */
final class AnthropicListingEnricher implements ListingEnricher
{
    private const TOOL = 'record_listing';

    private const INSTRUCTIONS = <<<'TEXT'
        You read long-term rental listings for flats in Odesa, Ukraine, written in Ukrainian or Russian, and record what they state by calling record_listing exactly once.

        Use only what the listing says. When it does not say, return null, or unknown for yes/no fields; never guess from typical values.

        Yes/no fields: answer yes or no only when the listing explicitly states it. A listing that does not mention a generator, backup power or outages gets unknown for all of them. Do not infer from the heating type, gas, the building's age, the yard or anything else: "central heating" says nothing about outages, and "a quiet yard" says nothing about a closed territory.

        - complex, street, house, district: the residential complex, street, house number and district as the listing or its address line names them, in the listing's spelling.
        - rooms, floor, floors_total, area: as stated; area in square metres.
        - price, currency: the monthly rent and its currency (UAH, USD or EUR), not a deposit or commission.
        - generator: yes if the building has a generator or backup power that keeps its utilities running during outages (water pumps, heating, lift), no only if the listing says the building has none. Backup only for the flat itself ("генератор на квартиру", an EcoFlow, inverter, batteries, power station) does not count: answer unknown for it. Heating from a gas boiler or water that works without power is not a "no": answer unknown.
        - generator_water, generator_heating, generator_elevator: yes or no for whether water, heating and the lift keep working during power outages.
        - generator_schedule: round_the_clock when backup power runs all the time, daytime when it runs during the day, scheduled when only for some hours, unknown when not said.
        - closed_territory: yes for a closed or guarded territory (gated yard, security, barrier), no if the listing says it is open.
        - is_owner: yes when the owner lets the flat directly, no when an agent or agency does or a commission is charged.
        - location_id: the id of the sector entry below where the flat is. Match names and aliases across Ukrainian and Russian spellings, old and new street names, abbreviations and typos. Numbers are part of a name: complexes of one series with different numbers are different places ("53 Жемчужина" is not "54-я Жемчужина", "Перлина" is the Ukrainian "Жемчужина"). Prefer a street or complex entry; pick a district entry only when the listing names no street or complex. Null when the flat is not in the list or the listing names no place.

        Sector entries, one per line as id | kind | name | aliases:
        TEXT;

    /** Token usage of the last answer, for cost reports */
    public ?Usage $lastUsage = null;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {}

    public function enrich(Listing $listing): Enrichment
    {
        $message = $this->client->messages->create(
            maxTokens: 1024,
            messages: [['role' => 'user', 'content' => $this->listingText($listing)]],
            model: $this->model,
            system: [[
                'type' => 'text',
                'text' => self::INSTRUCTIONS."\n".$this->sectorLines(),
                'cacheControl' => ['type' => 'ephemeral'],
            ]],
            temperature: 0.0,
            toolChoice: ['type' => 'tool', 'name' => self::TOOL],
            tools: [$this->tool()],
        );

        $this->lastUsage = $message->usage;

        return Enrichment::fromArray($this->toolInput($message));
    }

    /**
     * @return array<string, mixed>
     */
    private function toolInput(Message $message): array
    {
        if ($message->stopReason === 'refusal') {
            throw new EnrichmentFailed('The model refused: '.($message->stopDetails?->category ?? 'no category'));
        }

        foreach ($message->content as $block) {
            if ($block instanceof ToolUseBlock && $block->name === self::TOOL) {
                return $block->input;
            }
        }

        throw new EnrichmentFailed("No {$this->tool()['name']} call in the answer (stop reason: {$message->stopReason}).");
    }

    private function listingText(Listing $listing): string
    {
        return implode("\n\n", array_filter([
            $listing->title !== null ? "Title: {$listing->title}" : null,
            $listing->address !== null ? "Address from the site: {$listing->address}" : null,
            'Description:'."\n".($listing->description ?? '(none)'),
        ]));
    }

    /**
     * Sorted by id so the cached prompt only changes when the whitelist does.
     */
    private function sectorLines(): string
    {
        $lines = Location::query()->orderBy('id')->get()->map(fn (Location $location) => implode(' | ', [
            $location->id,
            $location->kind->value,
            $location->name,
            implode(', ', $location->aliases ?? []),
        ]));

        return $lines->isEmpty() ? '(none yet)' : $lines->implode("\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        $string = ['type' => ['string', 'null']];
        $integer = ['type' => ['integer', 'null']];
        // Yes/no fields are plain enums: strict schemas allow at most 16 nullable fields
        $yesNo = ['type' => 'string', 'enum' => ['yes', 'no', 'unknown']];

        $properties = [
            'complex' => $string,
            'street' => $string,
            'house' => $string,
            'district' => $string,
            'rooms' => $integer,
            'floor' => $integer,
            'floors_total' => $integer,
            'area' => ['type' => ['number', 'null']],
            'price' => $integer,
            // A strict schema rejects an enum on a ['string', 'null'] type
            'currency' => ['anyOf' => [['type' => 'string', 'enum' => ['UAH', 'USD', 'EUR']], ['type' => 'null']]],
            'generator' => $yesNo,
            'generator_water' => $yesNo,
            'generator_heating' => $yesNo,
            'generator_elevator' => $yesNo,
            'generator_schedule' => ['type' => 'string', 'enum' => array_column(GeneratorSchedule::cases(), 'value')],
            'closed_territory' => $yesNo,
            'is_owner' => $yesNo,
            'location_id' => $integer,
        ];

        return [
            'name' => self::TOOL,
            'description' => 'Record the facts the rental listing states.',
            'strict' => true,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => array_keys($properties),
                'additionalProperties' => false,
            ],
        ];
    }
}

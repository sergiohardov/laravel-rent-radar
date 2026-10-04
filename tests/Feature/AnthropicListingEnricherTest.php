<?php

use App\Currency\CurrencyCode;
use App\Enrichment\AnthropicListingEnricher;
use App\Enrichment\EnrichmentFailed;
use App\Enrichment\GeneratorSchedule;
use App\Enrichment\ListingEnricher;
use App\Location\LocationKind;
use App\Models\Listing;
use App\Models\Location;

/**
 * A Messages API answer with one record_listing call.
 */
function toolAnswer(array $input, string $stopReason = 'tool_use'): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-haiku-4-5',
        'content' => [['type' => 'tool_use', 'id' => 'toolu_test', 'name' => 'record_listing', 'input' => $input]],
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 1500, 'output_tokens' => 180, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 1200],
    ];
}

/**
 * Tool input as the model returns it: yes/no/unknown for flags.
 */
function fullInput(array $overrides = []): array
{
    return array_merge([
        'complex' => 'ЖК Фонтан', 'street' => 'Макаренко', 'house' => '2а', 'district' => 'Київський',
        'rooms' => 2, 'floor' => 9, 'floors_total' => 16, 'area' => 90.5, 'price' => 20000, 'currency' => 'UAH',
        'generator' => 'yes', 'generator_water' => 'yes', 'generator_heating' => 'unknown', 'generator_elevator' => 'yes',
        'generator_schedule' => 'round_the_clock', 'closed_territory' => 'yes', 'is_owner' => 'no', 'location_id' => 7,
    ], $overrides);
}

function enrichListing(array $attributes = []): \App\Enrichment\Enrichment
{
    return app(AnthropicListingEnricher::class)->enrich(Listing::factory()->make(array_merge([
        'title' => 'Сдам 2ую квартиру ЖК Фонтан, Макаренко 2а/Паркинг, Генератор',
        'address' => 'Київський',
        'description' => 'Генератор на весь дом, вода и лифт работают.',
    ], $attributes)));
}

it('turns the tool call into an enrichment', function () {
    $this->anthropic->push(toolAnswer(fullInput()));

    $enrichment = enrichListing();

    expect($enrichment->complex)->toBe('ЖК Фонтан')
        ->and($enrichment->rooms)->toBe(2)
        ->and($enrichment->area)->toBe(90.5)
        ->and($enrichment->currency)->toBe(CurrencyCode::Uah)
        ->and($enrichment->generator)->toBeTrue()
        ->and($enrichment->generatorHeating)->toBeNull()
        ->and($enrichment->generatorSchedule)->toBe(GeneratorSchedule::RoundTheClock)
        ->and($enrichment->isOwner)->toBeFalse()
        ->and($enrichment->locationId)->toBe(7)
        ->and($enrichment->toArray())->toEqual(array_merge(fullInput(), [
            'generator' => true, 'generator_water' => true, 'generator_heating' => null, 'generator_elevator' => true,
            'closed_territory' => true, 'is_owner' => false,
        ]));
});

it('keeps nulls as "the text does not say"', function () {
    $this->anthropic->push(toolAnswer(fullInput(['generator' => 'unknown', 'generator_schedule' => 'unknown', 'location_id' => null, 'currency' => null])));

    $enrichment = enrichListing();

    expect($enrichment->generator)->toBeNull()
        ->and($enrichment->generatorSchedule)->toBe(GeneratorSchedule::Unknown)
        ->and($enrichment->locationId)->toBeNull()
        ->and($enrichment->currency)->toBeNull();
});

it('sends the listing, a forced strict tool and the cached sector list', function () {
    Location::factory()->create(['kind' => LocationKind::Complex, 'name' => 'ЖК 32 Перлина', 'aliases' => ['32 Жемчужина']]);
    $this->anthropic->push(toolAnswer(fullInput()));

    enrichListing();

    $request = $this->anthropic->sent[0];
    $location = Location::query()->sole();
    expect($request['model'])->toBe('claude-haiku-4-5')
        ->and($request['tool_choice'])->toBe(['type' => 'tool', 'name' => 'record_listing'])
        ->and($request['tools'][0]['strict'])->toBeTrue()
        ->and($request['tools'][0]['input_schema']['additionalProperties'])->toBeFalse()
        ->and($request['tools'][0]['input_schema']['required'])->toContain('location_id', 'generator_schedule')
        // strict schemas: at most 16 nullable fields, no enum on a nullable type
        ->and($request['tools'][0]['input_schema']['properties']['generator'])->toBe(['type' => 'string', 'enum' => ['yes', 'no', 'unknown']])
        ->and($request['tools'][0]['input_schema']['properties']['currency'])->toHaveKey('anyOf')
        ->and($request['system'][0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($request['system'][0]['text'])->toContain("{$location->id} | complex | ЖК 32 Перлина | 32 Жемчужина")
        ->and($request['messages'][0]['content'])->toContain('Title: Сдам 2ую квартиру ЖК Фонтан')
        ->and($request['messages'][0]['content'])->toContain('Address from the site: Київський')
        ->and($request['messages'][0]['content'])->toContain('Генератор на весь дом');
});

it('keeps the system prompt identical between listings for caching', function () {
    $this->anthropic->push(toolAnswer(fullInput()))->push(toolAnswer(fullInput()));

    enrichListing(['title' => 'one']);
    enrichListing(['title' => 'two', 'description' => 'other']);

    expect($this->anthropic->sent[0]['system'])->toBe($this->anthropic->sent[1]['system']);
});

it('fails when the model refuses', function () {
    $this->anthropic->push(array_merge(toolAnswer(fullInput()), [
        'content' => [], 'stop_reason' => 'refusal', 'stop_details' => ['type' => 'refusal', 'category' => null, 'explanation' => null],
    ]));

    enrichListing();
})->throws(EnrichmentFailed::class, 'The model refused');

it('fails when the answer has no tool call', function () {
    $this->anthropic->push(array_merge(toolAnswer(fullInput()), [
        'content' => [['type' => 'text', 'text' => 'Sorry']], 'stop_reason' => 'end_turn',
    ]));

    enrichListing();
})->throws(EnrichmentFailed::class, 'No record_listing call in the answer (stop reason: end_turn).');

it('keeps the strict schema within 16 nullable fields', function () {
    $this->anthropic->push(toolAnswer(fullInput()));

    enrichListing();

    $nullable = collect($this->anthropic->sent[0]['tools'][0]['input_schema']['properties'])
        ->filter(fn (array $property) => is_array($property['type'] ?? null) || isset($property['anyOf']));
    expect($nullable->count())->toBeLessThanOrEqual(16);
});

it('is the enricher the app binds', function () {
    $this->app->forgetInstance(ListingEnricher::class);
    (new \App\Providers\AppServiceProvider($this->app))->register();

    expect(app(ListingEnricher::class))->toBeInstanceOf(AnthropicListingEnricher::class);
});

<?php

use App\Models\Location;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->casesFile = 'storage/framework/testing/llm-eval-cases.json';
    File::put(base_path($this->casesFile), json_encode([
        [
            'id' => 'olx-1',
            'note' => 'generator, Ойстраха in sector',
            'listing' => ['title' => 'Здам 2к на Ойстраха', 'address' => 'вул. Давида Ойстраха, 4', 'description' => 'Генератор на воду і опалення.'],
            'expect' => ['generator' => true, 'generator_water' => true, 'sector' => 'in'],
        ],
        [
            'id' => 'olx-2',
            'note' => 'no outage words',
            'listing' => ['title' => 'Сдам 1к', 'address' => null, 'description' => 'Тихий двор.'],
            'expect' => ['generator' => null, 'sector' => 'out'],
        ],
    ]));
});

afterEach(fn () => File::delete(base_path($this->casesFile)));

function evalAnswer(array $input, array $usage): array
{
    return [
        'id' => 'msg_eval', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-4-5',
        'content' => [['type' => 'tool_use', 'id' => 'toolu_eval', 'name' => 'record_listing', 'input' => $input]],
        'stop_reason' => 'tool_use', 'stop_sequence' => null, 'usage' => $usage,
    ];
}

it('reports accuracy per field and the cost per listing', function () {
    $ojstrakhaId = fn () => Location::query()->where('name', 'вулиця Давида Ойстраха')->value('id');
    $base = (new \App\Enrichment\Enrichment)->toArray();
    $flags = ['generator' => 'unknown', 'generator_water' => 'unknown', 'generator_heating' => 'unknown', 'generator_elevator' => 'unknown', 'closed_territory' => 'unknown', 'is_owner' => 'unknown'];

    // ids are only known once the command loaded the whitelist; the fake answers lazily
    $this->anthropic->pushLazy(fn () => evalAnswer(array_merge($base, $flags, ['generator' => 'yes', 'generator_water' => 'yes', 'location_id' => $ojstrakhaId()]),
        ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0]));
    // wrong on purpose: says "no generator" for a listing that does not mention it
    $this->anthropic->push(evalAnswer(array_merge($base, $flags, ['generator' => 'no']),
        ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0]));

    $this->artisan("llm:eval --cases={$this->casesFile}")
        ->expectsOutputToContain('ok   olx-1')
        ->expectsOutputToContain('MISS olx-2 — generator: expected null, got false')
        // one output line satisfies one expectation, so the cost line is checked whole
        ->expectsOutputToContain('claude-haiku-4-5: 2 listings, tokens in 2000, out 400, cache read 0, cache write 0; $0.0040 in total, $0.0020 per listing')
        ->assertSuccessful();
});

it('leaves the real whitelist untouched', function () {
    Location::factory()->create(['name' => 'вулиця Справжня']);
    $this->anthropic->push(evalAnswer((new \App\Enrichment\Enrichment)->toArray(), ['input_tokens' => 1, 'output_tokens' => 1]))
        ->push(evalAnswer((new \App\Enrichment\Enrichment)->toArray(), ['input_tokens' => 1, 'output_tokens' => 1]));

    $this->artisan("llm:eval --cases={$this->casesFile}")->assertSuccessful();

    expect(Location::query()->pluck('name')->all())->toBe(['вулиця Справжня']);
});

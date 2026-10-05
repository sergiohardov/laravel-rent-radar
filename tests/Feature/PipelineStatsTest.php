<?php

use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\PipelineStats;

beforeEach(function () {
    $this->freezeSecond();
});

function journal(array $attributes): Listing
{
    return Listing::factory()->create($attributes);
}

it('counts how far the listings of the last day got', function () {
    journal(['decision' => Decision::Rejected, 'rejected_by' => 'hard_filter', 'reject_reason' => 'rooms 1 not in 2–2']);
    journal(['decision' => Decision::Rejected, 'rejected_by' => 'location_matcher', 'reject_reason' => 'not in sector: x', 'enriched_at' => now()]);
    journal(['decision' => Decision::Pending, 'enriched_at' => now()]);
    journal(['decision' => Decision::Notified, 'enriched_at' => now(), 'notified_at' => now()]);
    journal(['decision' => Decision::Notified, 'created_at' => now()->subDays(2)]);

    expect(app(PipelineStats::class)->funnel(now()->subDay()))->toBe([
        'found' => 4,
        'passed_fields' => 3,
        'enriched' => 3,
        'in_sector' => 2,
        'sent' => 1,
    ]);
});

it('groups reject reasons, most frequent first', function () {
    journal(['decision' => Decision::Rejected, 'reject_reason' => 'floor 9 not in 1–6']);
    journal(['decision' => Decision::Rejected, 'reject_reason' => 'floor 12 not in 1–6']);
    journal(['decision' => Decision::Rejected, 'reject_reason' => 'not in sector: Таїрова']);
    journal(['decision' => Decision::Rejected, 'reject_reason' => 'something new']);
    journal(['decision' => Decision::Failed, 'reject_reason' => 'RateLimitException: 429']);
    journal(['decision' => Decision::Rejected, 'reject_reason' => 'floor 7 not in 1–6', 'created_at' => now()->subDays(8)]);

    expect(app(PipelineStats::class)->rejectReasons(now()->subDays(7)))->toBe([
        'Этаж' => 2,
        'Вне сектора' => 1,
        'Другое' => 1,
        'Сбой LLM' => 1,
    ]);
});

it('counts LLM calls by the time of the call', function () {
    journal(['enriched_at' => now()->subHours(2)]);
    journal(['enriched_at' => now()->subDays(3)]);
    journal(['enriched_at' => null]);

    expect(app(PipelineStats::class)->llmCalls(now()->subDay()))->toBe(1)
        ->and(app(PipelineStats::class)->llmCalls(now()->subDays(30)))->toBe(2);
});

it('measures median minutes from publishing to the bot and to the card', function () {
    journal(['published_at' => now()->subMinutes(10)]);
    journal(['published_at' => now()->subMinutes(20)]);
    journal(['published_at' => now()->subMinutes(30), 'notified_at' => now()->subMinutes(25)]);
    // A site clock ahead of ours is ignored
    journal(['published_at' => now()->addMinutes(5)]);
    // A first run's backlog says nothing about speed
    journal(['published_at' => now()->subDays(2)]);

    $stats = app(PipelineStats::class);
    expect($stats->medianDetectionMinutes(now()->subDay()))->toBe(20.0)
        ->and($stats->medianCardMinutes(now()->subDays(30)))->toBe(5.0);
});

it('has no median without data', function () {
    expect(app(PipelineStats::class)->medianCardMinutes(now()->subDays(30)))->toBeNull();
});

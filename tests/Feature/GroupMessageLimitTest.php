<?php

use App\Telegram\GroupMessageLimit;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A queued job stand-in: remembers whether it ran or went back to the queue.
 */
function limitedJob(): object
{
    return new class
    {
        public bool $ran = false;

        public ?int $releasedFor = null;

        public function release(int $delay): void
        {
            $this->releasedFor = $delay;
        }
    };
}

function throughLimit(int $messages): object
{
    $job = limitedJob();
    (new GroupMessageLimit($messages))->handle($job, function (object $job) {
        $job->ran = true;
    });

    return $job;
}

beforeEach(function () {
    RateLimiter::clear('telegram-group-messages');
    $this->freezeSecond();
});

it('lets messages through while the minute has room', function () {
    expect(throughLimit(10)->ran)->toBeTrue()
        ->and(throughLimit(9)->ran)->toBeTrue()
        ->and(throughLimit(1)->ran)->toBeTrue();
});

it('puts back an album that does not fit until the minute ends', function () {
    throughLimit(10);
    throughLimit(5);
    $this->travel(20)->seconds();

    $job = throughLimit(10);

    expect($job->ran)->toBeFalse()
        ->and($job->releasedFor)->toBe(40);
});

it('lets the album through in the next minute', function () {
    throughLimit(15);
    $this->travel(61)->seconds();

    expect(throughLimit(10)->ran)->toBeTrue();
});

<?php

namespace App\Telegram;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Queue middleware for posts to the group: Telegram allows about 20 messages
 * a minute there and counts every photo of an album as a message. A job that
 * does not fit into the current minute goes back to the queue until it ends.
 */
final class GroupMessageLimit
{
    public const PER_MINUTE = 20;

    private const KEY = 'telegram-group-messages';

    public function __construct(
        private readonly int $messages,
    ) {}

    /**
     * @param  object{release: callable}  $job
     */
    public function handle(object $job, Closure $next): void
    {
        $sent = RateLimiter::attempts(self::KEY);

        if ($sent > 0 && $sent + $this->messages > self::PER_MINUTE) {
            $job->release(max(1, RateLimiter::availableIn(self::KEY)));

            return;
        }

        RateLimiter::increment(self::KEY, 60, $this->messages);

        $next($job);
    }
}

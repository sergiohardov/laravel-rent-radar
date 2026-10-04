<?php

namespace Tests\Fakes;

use App\Models\Listing;
use App\Pipeline\Stage;
use App\Pipeline\StageResult;
use Closure;

/**
 * Stage driven by a closure; remembers the external ids it handled.
 */
final class FakeStage implements Stage
{
    /** @var list<string> */
    public array $handled = [];

    /**
     * @param  Closure(Listing): StageResult  $decide
     */
    public function __construct(
        private readonly string $name,
        private readonly Closure $decide,
    ) {}

    public static function passing(string $name = 'fake_pass'): self
    {
        return new self($name, fn () => StageResult::pass());
    }

    public function name(): string
    {
        return $this->name;
    }

    public function handle(Listing $listing): StageResult
    {
        $this->handled[] = $listing->external_id;

        return ($this->decide)($listing);
    }
}

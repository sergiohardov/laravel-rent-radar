<?php

namespace Tests\Fakes;

use Closure;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * PSR-18 transport for the Anthropic SDK in tests. With nothing queued, any
 * request fails, like Http::preventStrayRequests() does for Laravel's client.
 */
final class FakeAnthropicTransport implements ClientInterface
{
    /** @var list<ResponseInterface|Closure(): array<string, mixed>> */
    private array $queue = [];

    /** @var list<array<string, mixed>> decoded JSON bodies of sent requests */
    public array $sent = [];

    /**
     * @param  array<string, mixed>  $body
     */
    public function push(array $body, int $status = 200): self
    {
        $this->queue[] = new Response($status, ['Content-Type' => 'application/json'], json_encode($body));

        return $this;
    }

    /**
     * A 200 answer built when the request arrives, for bodies that depend on
     * state created after the test queued it.
     *
     * @param  Closure(): array<string, mixed>  $body
     */
    public function pushLazy(Closure $body): self
    {
        $this->queue[] = $body;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = json_decode((string) $request->getBody(), true);

        $next = array_shift($this->queue)
            ?? throw new RuntimeException('Attempted request to the Anthropic API without a faked response: '.$request->getUri());

        return $next instanceof Closure
            ? new Response(200, ['Content-Type' => 'application/json'], json_encode($next()))
            : $next;
    }
}

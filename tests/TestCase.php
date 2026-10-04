<?php

namespace Tests;

use Anthropic\Client as AnthropicClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeAnthropicTransport;

abstract class TestCase extends BaseTestCase
{
    protected FakeAnthropicTransport $anthropic;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never reach real listing sites, the LLM or Telegram.
        Http::preventStrayRequests();

        // The Anthropic SDK has its own transport, out of Http's reach
        $this->anthropic = new FakeAnthropicTransport;
        $this->app->instance(AnthropicClient::class, new AnthropicClient(
            apiKey: 'test-key',
            requestOptions: ['transporter' => $this->anthropic, 'maxRetries' => 0],
        ));
    }
}

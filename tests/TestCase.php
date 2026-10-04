<?php

namespace Tests;

use Anthropic\Client as AnthropicClient;
use App\Enrichment\ListingEnricher;
use App\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeAnthropicTransport;
use Tests\Fakes\FakeListingEnricher;
use Tests\Fakes\FakeTelegramNotifier;

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

        // Pipeline runs skip the LLM and Telegram unless a test asks for the real ones
        $this->app->instance(ListingEnricher::class, new FakeListingEnricher);
        $this->app->instance(TelegramNotifier::class, new FakeTelegramNotifier);
    }
}

<?php

use Anthropic\Client as AnthropicClient;

it('blocks real Anthropic API requests in tests', function () {
    app(AnthropicClient::class)->messages->create(
        maxTokens: 16,
        messages: [['role' => 'user', 'content' => 'ping']],
        model: 'claude-haiku-4-5',
    );
})->throws(RuntimeException::class, 'Attempted request to the Anthropic API without a faked response');

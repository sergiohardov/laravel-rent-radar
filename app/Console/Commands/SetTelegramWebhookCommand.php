<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {--url= : Public base URL; by default the dev tunnel, else APP_URL}';

    protected $description = 'Point the bot webhook at this app, with the secret token header';

    public function handle(Nutgram $bot): int
    {
        $base = $this->option('url') ?: $this->tunnelUrl() ?: config('app.url');
        $url = rtrim((string) $base, '/').route('telegram.webhook', absolute: false);

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram needs an HTTPS webhook, got {$url}. Start the tunnel: docker compose --profile tunnel up -d");

            return self::FAILURE;
        }

        $bot->setWebhook(url: $url, secret_token: md5((string) config('app.key')), drop_pending_updates: true);

        $info = $bot->getWebhookInfo();
        $this->info("Webhook: {$info->url}");

        return self::SUCCESS;
    }

    /**
     * The quick tunnel's current hostname: it changes on every tunnel start.
     */
    private function tunnelUrl(): ?string
    {
        $metrics = config('services.telegram.tunnel_metrics_url');
        if (blank($metrics)) {
            return null;
        }

        try {
            $hostname = Http::timeout(3)->get($metrics)->json('hostname');
        } catch (ConnectionException) {
            return null;
        }

        return filled($hostname) ? "https://{$hostname}" : null;
    }
}

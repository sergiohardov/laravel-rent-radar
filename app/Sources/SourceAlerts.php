<?php

namespace App\Sources;

use App\Models\Source;
use App\Telegram\SendAlert;
use Carbon\CarbonImmutable;

/**
 * Tells the group when a source stops working and when it is back. Each
 * alert goes once per episode, not on every failed run.
 */
final class SourceAlerts
{
    public function failed(Source $state): void
    {
        if ($state->consecutive_failures !== $this->threshold()) {
            return;
        }

        $this->send('source-failing', [
            'source' => $state->name->label(),
            'failures' => $state->consecutive_failures,
            'error' => $state->last_error,
            'lastSuccess' => $this->kyiv($state->last_success_at),
        ]);
    }

    public function succeeded(Source $state, int $failuresBefore): void
    {
        if ($failuresBefore < $this->threshold()) {
            return;
        }

        $this->send('source-recovered', [
            'source' => $state->name->label(),
            'failures' => $failuresBefore,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(string $view, array $data): void
    {
        SendAlert::dispatch(trim(view("telegram.alerts.{$view}", $data)->render()));
    }

    private function threshold(): int
    {
        return (int) config('sources.alert_after_failures');
    }

    /**
     * "04.10 16:55" in Odesa time.
     */
    private function kyiv(?CarbonImmutable $time): ?string
    {
        return $time?->setTimezone('Europe/Kyiv')->format('d.m H:i');
    }
}

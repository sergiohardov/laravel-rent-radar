<?php

namespace App\Sources;

use App\Models\Listing;
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
            'source' => $state->name->getLabel(),
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
            'source' => $state->name->getLabel(),
            'failures' => $failuresBefore,
        ]);
    }

    /**
     * Alerts when an enabled source brought no new listings for the silence
     * window, counting from its first run when it never brought any.
     *
     * @return bool whether the alert went out
     */
    public function checkSilence(Source $state, CarbonImmutable $now): bool
    {
        if (! $state->enabled || $state->last_run_at === null) {
            return false;
        }

        $lastNew = Listing::query()->where('source', $state->name)->latest()->value('created_at')?->toImmutable();
        $silentSince = $lastNew ?? $state->created_at->toImmutable();
        $hours = (int) config('sources.silence_alert_hours');

        $silent = $silentSince->lte($now->subHours($hours));
        $alreadyAlerted = $state->silence_alerted_at?->gte($silentSince) ?? false;
        if (! $silent || $alreadyAlerted) {
            return false;
        }

        $this->send('source-silent', [
            'source' => $state->name->getLabel(),
            'hours' => $hours,
            'lastNew' => $this->kyiv($lastNew),
            'lastRun' => $this->kyiv($state->last_run_at),
            'failures' => $state->consecutive_failures,
            'error' => $state->last_error,
        ]);
        $state->update(['silence_alerted_at' => $now]);

        return true;
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

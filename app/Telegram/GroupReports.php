<?php

namespace App\Telegram;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Pipeline\PipelineStats;
use App\Sources\SourceName;
use App\Sources\SourceState;
use App\Support\Minutes;

/**
 * Answers to the group's commands, from resources/views/telegram/commands:
 * the same numbers as the admin dashboard.
 */
final class GroupReports
{
    private const BAR_LENGTH = 10;

    public function __construct(
        private readonly PipelineStats $stats,
    ) {}

    public function status(): string
    {
        $sources = array_map(function (SourceName $name) {
            $source = Source::for($name);
            $state = SourceState::of($source);

            return [
                'emoji' => $state->emoji(),
                'name' => $name->getLabel(),
                'state' => $state->label($source),
                'details' => implode(' · ', array_filter([
                    $source->last_run_at ? 'запуск '.$source->last_run_at->setTimezone('Europe/Kyiv')->format('d.m H:i') : null,
                    SourceResource::reportSummary($source->last_report),
                ])),
            ];
        }, SourceName::cases());

        return $this->render('status', ['sources' => $sources]);
    }

    public function funnel(): string
    {
        return $this->render('funnel', [
            'funnel' => $this->stats->funnel(now()->subDay()),
            'detection' => Minutes::human($this->stats->medianDetectionMinutes(now()->subDay())),
            'card' => Minutes::human($this->stats->medianCardMinutes(now()->subDays(30))),
        ]);
    }

    public function reasons(): string
    {
        $reasons = $this->stats->rejectReasons(now()->subDays(7));
        $max = max([1, ...array_values($reasons)]);

        $rows = [];
        foreach ($reasons as $label => $count) {
            $rows[] = [
                'bar' => str_repeat('▇', max(1, (int) round($count / $max * self::BAR_LENGTH))),
                'label' => $label,
                'count' => $count,
            ];
        }

        return $this->render('reasons', ['rows' => $rows]);
    }

    public function help(): string
    {
        return $this->render('help');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data = []): string
    {
        $html = view("telegram.commands.{$view}", $data)->render();

        // Blade leaves indentation and blank lines around directives; Telegram shows them
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/^[ \t]+|[ \t]+$/m', '', $html)));
    }
}

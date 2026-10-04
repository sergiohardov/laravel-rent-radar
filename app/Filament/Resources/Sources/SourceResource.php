<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\ManageSources;
use App\Models\Source;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/**
 * Listing sources: on/off, proxy, run interval and run state. The sources
 * themselves are code (App\Sources\SourceName), so there is no create or delete.
 */
class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Источники';

    protected static ?string $modelLabel = 'источник';

    protected static ?string $pluralModelLabel = 'источники';

    protected static ?int $navigationSort = 3;

    private const TIME_FORMAT = 'd.m H:i';

    private const TIMEZONE = 'Europe/Kyiv';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Toggle::make('enabled')->label('Включён'),
                Toggle::make('use_proxy')
                    ->label('Через прокси')
                    ->helperText(fn () => filled(config('sources.proxy_url'))
                        ? 'Нужен на проде для ЛУН и OLX: они не отвечают зарубежному IP хостинга.'
                        : 'SOURCES_PROXY_URL в .env пуст — с включённым прокси запуски будут падать.'),
                Grid::make(2)->schema([
                    TextInput::make('interval_min_minutes')
                        ->label('Интервал от, мин')
                        ->integer()->required()->minValue(1)->maxValue(1440)
                        ->lte('interval_max_minutes'),
                    TextInput::make('interval_max_minutes')
                        ->label('Интервал до, мин')
                        ->integer()->required()->minValue(1)->maxValue(1440)
                        ->helperText('Следующий запуск — в случайный момент внутри интервала.'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Площадка')->weight('bold'),
                ToggleColumn::make('enabled')->label('Вкл'),
                ToggleColumn::make('use_proxy')->label('Прокси'),
                TextColumn::make('interval_min_minutes')
                    ->label('Интервал')
                    ->formatStateUsing(fn (Source $record) => "{$record->interval_min_minutes}–{$record->interval_max_minutes} мин"),
                TextColumn::make('last_run_at')
                    ->label('Последний запуск')
                    ->dateTime(self::TIME_FORMAT, self::TIMEZONE)
                    ->placeholder('не было')
                    ->description(fn (Source $record) => self::reportSummary($record->last_report)),
                TextColumn::make('next_run_at')
                    ->label('Следующий')
                    ->dateTime(self::TIME_FORMAT, self::TIMEZONE)
                    ->placeholder('при первом тике')
                    ->description(fn (Source $record) => $record->enabled ? null : 'выключен'),
                TextColumn::make('consecutive_failures')
                    ->label('Ошибок подряд')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state >= (int) config('sources.alert_after_failures') => 'danger',
                        $state > 0 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('last_error')
                    ->label('Последняя ошибка')
                    ->placeholder('—')
                    // An old error of a source that works again is history, not a problem
                    ->color(fn (Source $record) => $record->consecutive_failures > 0 ? 'danger' : 'gray')
                    ->limit(60)
                    ->tooltip(fn (Source $record) => $record->last_error)
                    ->wrap()
                    ->description(fn (Source $record) => $record->last_error_at?->setTimezone(self::TIMEZONE)->format(self::TIME_FORMAT)),
            ])
            ->recordActions([
                Action::make('runNow')
                    ->label('Запустить сейчас')
                    ->tooltip('Запустить сейчас')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->visible(fn (Source $record) => $record->enabled)
                    ->action(function (Source $record): void {
                        $record->update(['next_run_at' => now()]);

                        Notification::make()
                            ->success()
                            ->title("{$record->name->getLabel()} запустится в течение минуты")
                            ->send();
                    }),
                EditAction::make()->iconButton()->tooltip('Изменить'),
            ]);
    }

    /**
     * "новых 2, прошли 1 · 3.2 с" from the last successful run.
     *
     * @param  array<string, mixed>|null  $report
     */
    public static function reportSummary(?array $report): ?string
    {
        if ($report === null) {
            return null;
        }

        return sprintf(
            'новых %d, прошли %d, отсеяно %d%s · %s с',
            $report['created'] ?? 0,
            $report['passed'] ?? 0,
            $report['rejected'] ?? 0,
            ($report['failed'] ?? 0) > 0 ? ", сбой LLM {$report['failed']}" : '',
            $report['seconds'] ?? 0,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSources::route('/'),
        ];
    }
}

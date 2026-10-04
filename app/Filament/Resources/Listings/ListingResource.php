<?php

namespace App\Filament\Resources\Listings;

use App\Currency\CurrencyCode;
use App\Enrichment\GeneratorSchedule;
use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Models\Listing;
use App\Pipeline\Flag;
use App\Sources\SourceName;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The journal of every listing the pipeline saw: its decision, the stage that
 * rejected it and why, and what the LLM read from the text. Read-only.
 */
class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Журнал';

    protected static ?string $modelLabel = 'объявление';

    protected static ?string $pluralModelLabel = 'журнал объявлений';

    protected static ?int $navigationSort = 4;

    protected static bool $hasTitleCaseModelLabel = false;

    private const TIME_FORMAT = 'd.m H:i';

    private const TIMEZONE = 'Europe/Kyiv';

    /**
     * Pipeline stage names (App\Pipeline\Stage::name()) for people.
     */
    public const STAGES = [
        'hard_filter' => 'Фильтр по полям',
        'llm_enricher' => 'LLM',
        'hard_filter_text' => 'Фильтр по тексту',
        'location_matcher' => 'Сектор',
        'telegram_notifier' => 'Telegram',
    ];

    /**
     * Reject reasons grouped by what failed; patterns follow the stages' reason texts.
     *
     * @var array<string, array{label: string, patterns: list<string>}>
     */
    public const REASONS = [
        'rooms' => ['label' => 'Комнаты', 'patterns' => ['rooms %']],
        'floor' => ['label' => 'Этаж', 'patterns' => ['floor %']],
        'price' => ['label' => 'Цена', 'patterns' => ['price %']],
        'generator' => ['label' => 'Генератор', 'patterns' => ['no generator%', 'generator unknown%']],
        'outages' => ['label' => 'Вода или тепло при отключениях', 'patterns' => ['no water%', 'no heating%']],
        'sector' => ['label' => 'Вне сектора', 'patterns' => ['not in sector%']],
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('created_at')->label('Найдено')->dateTime(self::TIME_FORMAT, self::TIMEZONE)->sortable(),
                TextColumn::make('source')->label('Площадка')->badge()->color('gray'),
                TextColumn::make('decision')->label('Решение')->badge(),
                TextColumn::make('title')
                    ->label('Объявление')
                    ->state(fn (Listing $record) => $record->title ?? $record->address ?? '—')
                    ->description(fn (Listing $record) => $record->title !== null ? $record->address : null)
                    ->limit(60)
                    ->wrap()
                    ->searchable(['title', 'address', 'description']),
                TextColumn::make('price')
                    ->label('Цена')
                    ->state(fn (Listing $record) => self::money($record->price, $record->currency))
                    ->description(fn (Listing $record) => $record->currency !== CurrencyCode::Uah && $record->price_uah !== null
                        ? '≈ '.self::money($record->price_uah, CurrencyCode::Uah)
                        : null)
                    ->placeholder('—'),
                TextColumn::make('rooms')->label('Комн')->placeholder('?'),
                TextColumn::make('floor')
                    ->label('Этаж')
                    ->state(fn (Listing $record) => $record->floor === null ? null : $record->floor.($record->floors_total ? "/{$record->floors_total}" : ''))
                    ->placeholder('?'),
                TextColumn::make('reject_reason')
                    ->label('Причина')
                    ->description(fn (Listing $record) => self::STAGES[$record->rejected_by] ?? $record->rejected_by)
                    ->placeholder('—')
                    ->limit(70)
                    ->tooltip(fn (Listing $record) => $record->reject_reason)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('flags')->label('Флаги')->badge()->color('warning')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('source')->label('Площадка')->options(SourceName::class),
                SelectFilter::make('rejected_by')->label('Стадия отсева')->options(self::STAGES),
                SelectFilter::make('reason')
                    ->label('Причина отсева')
                    ->options(array_map(fn (array $reason) => $reason['label'], self::REASONS))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        self::REASONS[$data['value'] ?? ''] ?? null,
                        fn (Builder $query, array $reason) => $query->where(function (Builder $query) use ($reason) {
                            foreach ($reason['patterns'] as $pattern) {
                                $query->orWhere('reject_reason', 'like', $pattern);
                            }
                        }),
                    )),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Открыть на сайте')
                    ->tooltip('Открыть на сайте')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Listing $record) => $record->url, shouldOpenInNewTab: true),
                ViewAction::make()
                    ->iconButton()
                    ->tooltip('Подробнее')
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalHeading(fn (Listing $record) => $record->title ?? $record->address ?? 'Объявление'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Решение')->columns(2)->schema([
                    TextEntry::make('decision')->label('Решение')->badge(),
                    TextEntry::make('rejected_by')
                        ->label('Стадия')
                        ->formatStateUsing(fn (string $state) => self::STAGES[$state] ?? $state)
                        ->placeholder('—'),
                    TextEntry::make('reject_reason')->label('Причина')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('flags')->label('Флаги')->badge()->color('warning')->placeholder('—'),
                    TextEntry::make('notified_at')->label('Отправлено в группу')->dateTime(self::TIME_FORMAT, self::TIMEZONE)->placeholder('—'),
                ]),
                Section::make('Объявление')->columns(2)->schema([
                    TextEntry::make('source')->label('Площадка'),
                    TextEntry::make('url')->label('Ссылка')->url(fn (?string $state) => $state, shouldOpenInNewTab: true)->limit(50),
                    TextEntry::make('published_at')->label('Опубликовано')->dateTime(self::TIME_FORMAT, self::TIMEZONE)->placeholder('—'),
                    TextEntry::make('created_at')->label('Найдено')->dateTime(self::TIME_FORMAT, self::TIMEZONE),
                    TextEntry::make('price')
                        ->label('Цена')
                        ->state(fn (Listing $record) => self::money($record->price, $record->currency))
                        ->placeholder('—'),
                    TextEntry::make('price_uah')->label('Цена, грн')->numeric(decimalPlaces: 0, thousandsSeparator: ' ')->placeholder('—'),
                    TextEntry::make('rooms')->label('Комнат')->placeholder('?'),
                    TextEntry::make('floor')
                        ->label('Этаж')
                        ->state(fn (Listing $record) => $record->floor === null ? null : $record->floor.($record->floors_total ? " из {$record->floors_total}" : ''))
                        ->placeholder('?'),
                    TextEntry::make('area')->label('Площадь, м²')->placeholder('?'),
                    TextEntry::make('address')->label('Адрес с площадки')->placeholder('—'),
                    TextEntry::make('title')->label('Заголовок')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('description')->label('Описание')->placeholder('—')->columnSpanFull()->prose(),
                ]),
                Section::make('Что прочитал LLM')
                    ->visible(fn (Listing $record) => $record->enrichment !== null)
                    ->schema([
                        Grid::make(2)->schema([
                            self::enrichment('sector_match.name', 'Совпадение с сектором'),
                            self::enrichment('complex', 'ЖК'),
                            self::enrichment('street', 'Улица'),
                            self::enrichment('house', 'Дом'),
                            self::enrichment('district', 'Район'),
                            self::mark('generator', '⚡ Генератор'),
                            self::mark('generator_water', '💧 Вода при отключениях'),
                            self::mark('generator_heating', '🔥 Тепло при отключениях'),
                            self::mark('generator_elevator', '🛗 Лифт при отключениях'),
                            self::enrichment('generator_schedule', '⏱ График генератора')
                                ->formatStateUsing(fn (string $state) => match (GeneratorSchedule::tryFrom($state)) {
                                    GeneratorSchedule::RoundTheClock => 'круглосуточно',
                                    GeneratorSchedule::Daytime => 'днём',
                                    GeneratorSchedule::Scheduled => 'по графику',
                                    default => 'не сказано',
                                }),
                            self::mark('closed_territory', '🔒 Закрытая территория'),
                            self::mark('is_owner', '👤 Собственник'),
                        ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageListings::route('/'),
        ];
    }

    private static function enrichment(string $key, string $label): TextEntry
    {
        return TextEntry::make("enrichment.{$key}")->label($label)->placeholder('—');
    }

    /**
     * A yes / no / not said flag from the LLM.
     */
    private static function mark(string $key, string $label): TextEntry
    {
        return TextEntry::make("enrichment.{$key}")
            ->label($label)
            ->state(fn (Listing $record) => match ($record->enrichment[$key] ?? null) {
                true => '✅ да',
                false => '❌ нет',
                default => '❓ не сказано',
            });
    }

    private static function money(?int $amount, ?CurrencyCode $currency): ?string
    {
        if ($amount === null || $currency === null) {
            return null;
        }

        return number_format($amount, 0, '.', ' ').' '.match ($currency) {
            CurrencyCode::Uah => 'грн',
            CurrencyCode::Usd => '$',
            CurrencyCode::Eur => '€',
        };
    }
}

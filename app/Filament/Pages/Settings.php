<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Search settings: hard filters, "pass when unknown" switches and the sector
 * point. The pipeline reads them at the start of each source run.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $title = 'Настройки поиска';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Квартира')
                    ->description('Жёсткие фильтры: объявление вне этих рамок отсеивается.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_price_uah')
                            ->label('Цена до, грн')
                            ->helperText('Цены в $ и € переводятся в гривну по курсу НБУ.')
                            ->integer()->required()->minValue(1)->maxValue(1_000_000)
                            ->columnSpanFull(),
                        TextInput::make('rooms_min')->label('Комнат от')->integer()->required()->minValue(1)->maxValue(10)->lte('rooms_max'),
                        TextInput::make('rooms_max')->label('Комнат до')->integer()->required()->minValue(1)->maxValue(10),
                        TextInput::make('floor_min')->label('Этаж от')->integer()->required()->minValue(-2)->maxValue(100)->lte('floor_max'),
                        TextInput::make('floor_max')->label('Этаж до')->integer()->required()->minValue(-2)->maxValue(100),
                        Toggle::make('allow_euro_two_room')
                            ->label('Пропускать евродвушку')
                            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: 'Площадки часто пишут евродвушку как 1 комнату. Включено — проверяются и квартиры от 1 комнаты, даже если в «Комнат от» стоит 2: проходит та, в тексте которой евродвушка (в любом написании) или кухня-студия/гостиная с отдельной спальней. Работает, когда «Комнат от» = 2.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Если значение неизвестно ❓')
                    ->description('Включено — объявление без этого значения проходит с ❓ в карточке. Выключено — отсеивается.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('allow_unknown_price')->label('Пропускать без цены'),
                        Toggle::make('allow_unknown_rooms')->label('Пропускать без числа комнат'),
                        Toggle::make('allow_unknown_floor')->label('Пропускать без этажа'),
                        Toggle::make('allow_unknown_generator')->label('Пропускать, если о генераторе не сказано'),
                    ]),

                Section::make('Тихие часы')
                    ->description('В эти часы карточки и алерты приходят в группу без звука. Время — по Киеву, интервал может переходить через полночь.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('quiet_hours_enabled')->label('Включить тихие часы')->live()->columnSpanFull(),
                        TimePicker::make('quiet_from')->label('С')->seconds(false)
                            ->required(fn (Get $get) => (bool) $get('quiet_hours_enabled'))
                            ->different('quiet_to'),
                        TimePicker::make('quiet_to')->label('До')->seconds(false)
                            ->required(fn (Get $get) => (bool) $get('quiet_hours_enabled')),
                    ]),

                Section::make('Сектор')
                    ->description('Точка и радиус нужны только для импорта улиц и ЖК из OpenStreetMap. Объявления проверяются по тексту, а не по координатам.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('center_lat')->label('Широта')->numeric()->minValue(-90)->maxValue(90)
                                ->requiredWith('center_lng,radius_m')->placeholder('46.579410'),
                            TextInput::make('center_lng')->label('Долгота')->numeric()->minValue(-180)->maxValue(180)
                                ->requiredWith('center_lat,radius_m')->placeholder('30.804915'),
                            TextInput::make('radius_m')->label('Радиус, м')->integer()->minValue(100)->maxValue(20_000)
                                ->requiredWith('center_lat,center_lng')->placeholder('1000'),
                        ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Сохранить')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        Setting::current()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Сохранено. Новые значения действуют со следующего запуска источника.')
            ->send();
    }
}

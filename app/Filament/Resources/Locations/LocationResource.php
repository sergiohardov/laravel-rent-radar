<?php

namespace App\Filament\Resources\Locations;

use App\Filament\Resources\Locations\Pages\ManageLocations;
use App\Location\LocationKind;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * The sector whitelist. The LLM gets every entry with its aliases in the
 * prompt and answers with the id of the one a listing names.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Сектор';

    protected static ?string $modelLabel = 'улица, ЖК или район';

    protected static ?string $pluralModelLabel = 'Сектор: улицы, ЖК, районы';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('kind')
                    ->label('Тип')
                    ->options(LocationKind::class)
                    ->default(LocationKind::Street)
                    ->helperText('Совпал только район — объявление проходит с 📍?.')
                    ->required(),
                TextInput::make('name')
                    ->label('Название')
                    ->helperText('Число в названии («54-я Жемчужина») должно быть и в тексте объявления.')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('kind', $get('kind'))),
                TagsInput::make('aliases')
                    ->label('Алиасы')
                    ->helperText('Русское и украинское, старое и новое название, сокращения. Enter после каждого.')
                    ->reorderable(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('kind')->label('Тип')->badge()->sortable(),
                TextColumn::make('name')->label('Название')->searchable()->sortable()->wrap(),
                TextColumn::make('aliases')
                    ->label('Алиасы')
                    ->badge()
                    ->color('gray')
                    ->limitList(4)
                    ->expandableLimitedList()
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereRaw('aliases::text ilike ?', ['%'.$search.'%'])),
            ])
            ->filters([
                SelectFilter::make('kind')->label('Тип')->options(LocationKind::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocations::route('/'),
        ];
    }
}

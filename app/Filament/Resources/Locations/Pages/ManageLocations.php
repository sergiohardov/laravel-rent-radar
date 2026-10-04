<?php

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Locations\LocationResource;
use App\Location\OsmLocationImporter;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\HttpClientException;

class ManageLocations extends ManageRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importOsmAction(),
            CreateAction::make(),
        ];
    }

    private function importOsmAction(): Action
    {
        return Action::make('importOsm')
            ->label('Импорт из OpenStreetMap')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Импорт из OpenStreetMap')
            ->modalDescription(function (): string {
                $settings = Setting::current();

                return $settings->hasSector()
                    ? "Улицы, ЖК и районы вокруг {$settings->center_lat}, {$settings->center_lng} в радиусе {$settings->radius_m} м. Существующие записи и алиасы, добавленные вручную, сохранятся."
                    : 'Сначала задайте точку и радиус сектора в настройках.';
            })
            ->modalSubmitAction(fn (Action $action) => $action->label('Импортировать')->disabled(! Setting::current()->hasSector()))
            ->extraModalFooterActions(fn () => Setting::current()->hasSector() ? [] : [
                Action::make('settings')->label('Открыть настройки')->url(Settings::getUrl()),
            ])
            ->action(function (OsmLocationImporter $importer): void {
                $settings = Setting::current();
                if (! $settings->hasSector()) {
                    return;
                }

                try {
                    $result = $importer->import($settings->center_lat, $settings->center_lng, $settings->radius_m);
                } catch (HttpClientException $e) {
                    Notification::make()->danger()->title('OpenStreetMap не ответил, попробуйте позже')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Найдено {$result['entries']}, новых {$result['added']}")
                    ->send();
            });
    }
}

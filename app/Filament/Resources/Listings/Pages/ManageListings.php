<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use App\Pipeline\Decision;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageListings extends ManageRecords
{
    protected static string $resource = ListingResource::class;

    protected static ?string $title = 'Журнал объявлений';

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Все')];

        foreach ([Decision::Notified, Decision::Pending, Decision::Rejected, Decision::Failed] as $decision) {
            $tabs[$decision->value] = Tab::make($decision->getLabel())
                ->badge(fn () => Listing::query()->where('decision', $decision)->count() ?: null)
                ->badgeColor($decision->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('decision', $decision));
        }

        return $tabs;
    }
}

<?php

namespace App\Filament\Resources\Sources\Pages;

use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Sources\SourceName;
use Filament\Resources\Pages\ManageRecords;

class ManageSources extends ManageRecords
{
    protected static string $resource = SourceResource::class;

    public function mount(): void
    {
        // State rows otherwise appear on a source's first run
        foreach (SourceName::cases() as $name) {
            Source::for($name);
        }

        parent::mount();
    }
}

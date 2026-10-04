<?php

use App\Currency\CurrencyCode;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Models\Listing;
use App\Models\User;
use App\Pipeline\Decision;
use App\Pipeline\Flag;
use App\Sources\SourceName;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function rejected(string $stage, string $reason, array $attributes = []): Listing
{
    return Listing::factory()->create(['decision' => Decision::Rejected, 'rejected_by' => $stage, 'reject_reason' => $reason, ...$attributes]);
}

it('shows the journal newest first', function () {
    $old = rejected('hard_filter', 'rooms 1 not in 2–2', ['created_at' => now()->subHour()]);
    $new = Listing::factory()->create([
        'decision' => Decision::Notified,
        'title' => 'Двушка на Заболотного',
        'price' => 400,
        'currency' => CurrencyCode::Usd,
        'price_uah' => 16600,
        'flags' => [Flag::UnknownGenerator],
    ]);

    $this->get(ManageListings::getUrl())->assertOk()
        ->assertSee(['Двушка на Заболотного', '400 $', '≈ 16 600 грн', 'генератор ❓', 'rooms 1 not in 2–2', 'Фильтр по полям']);

    Livewire::test(ManageListings::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

it('splits the journal into tabs by decision', function () {
    $notified = Listing::factory()->create(['decision' => Decision::Notified]);
    $failed = Listing::factory()->create(['decision' => Decision::Failed, 'rejected_by' => 'llm_enricher', 'reject_reason' => 'RateLimitException: 429']);
    $rejected = rejected('hard_filter', 'floor 9 not in 1–6');

    Livewire::test(ManageListings::class)
        ->set('activeTab', 'failed')
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$notified, $rejected]);
});

it('filters by the stage and the reason of rejection', function () {
    $rooms = rejected('hard_filter', 'rooms 3 not in 2–2');
    $price = rejected('hard_filter', 'price 400 USD = 16 600 UAH > 15 000 UAH');
    $sector = rejected('location_matcher', 'not in sector: вулиця Пастера · Приморський');
    $water = rejected('hard_filter_text', 'no water in outages');
    $generator = rejected('hard_filter_text', 'no generator');

    Livewire::test(ManageListings::class)
        ->filterTable('reason', 'price')
        ->assertCanSeeTableRecords([$price])
        ->assertCanNotSeeTableRecords([$rooms, $sector, $water, $generator])
        ->filterTable('reason', 'outages')
        ->assertCanSeeTableRecords([$water])
        ->assertCanNotSeeTableRecords([$generator])
        ->resetTableFilters()
        ->filterTable('rejected_by', 'location_matcher')
        ->assertCanSeeTableRecords([$sector])
        ->assertCanNotSeeTableRecords([$rooms, $price]);
});

it('filters by source and finds a listing by its text', function () {
    $olx = Listing::factory()->create(['source' => SourceName::Olx, 'title' => 'Квартира біля моря']);
    $lun = Listing::factory()->create(['source' => SourceName::Lun, 'address' => 'вулиця Академіка Заболотного, 57']);

    Livewire::test(ManageListings::class)
        ->filterTable('source', SourceName::Olx->value)
        ->assertCanSeeTableRecords([$olx])
        ->assertCanNotSeeTableRecords([$lun])
        ->resetTableFilters()
        ->searchTable('Заболотного')
        ->assertCanSeeTableRecords([$lun])
        ->assertCanNotSeeTableRecords([$olx]);
});

it('shows what the LLM read from the text', function () {
    $listing = Listing::factory()->create([
        'decision' => Decision::Notified,
        'enrichment' => [
            'street' => 'вулиця Академіка Заболотного',
            'sector_match' => ['id' => 12, 'kind' => 'street', 'name' => 'вулиця Академіка Заболотного'],
            'generator' => true,
            'generator_water' => false,
            'generator_schedule' => 'daytime',
        ],
    ]);

    Livewire::test(ManageListings::class)
        ->mountAction(TestAction::make('view')->table($listing))
        ->assertMountedActionModalSee(['Что прочитал LLM', 'вулиця Академіка Заболотного', '✅ да', '❌ нет', '❓ не сказано', 'днём']);
});

it('is read-only', function () {
    expect(ListingResource::canCreate())->toBeFalse();

    Livewire::test(ManageListings::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

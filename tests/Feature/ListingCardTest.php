<?php

use App\Currency\CurrencyCode;
use App\Enrichment\Enrichment;
use App\Enrichment\GeneratorSchedule;
use App\Models\Listing;
use App\Pipeline\Flag;
use App\Telegram\ListingCard;

function card(array $listing, array $enrichment = []): string
{
    $model = Listing::factory()->make(array_merge([
        'url' => 'https://www.olx.ua/d/uk/obyavlenie/zdam-2k-ID11oxAU.html',
        'price' => 400, 'currency' => CurrencyCode::Usd, 'price_uah' => 18000,
        'rooms' => 2, 'floor' => 4, 'floors_total' => 9, 'area' => 52.0,
        'address' => 'Пересипський',
    ], $listing));
    $model->enrichment = array_merge((new Enrichment)->toArray(), $enrichment);

    return app(ListingCard::class)->caption($model);
}

it('renders the full card in Russian with the marks as a list', function () {
    $caption = card([], [
        'complex' => 'Житловий комплекс "Гулівер"',
        'street' => 'вулиця Академіка Заболотного', 'house' => '34',
        'generator' => true, 'generator_water' => true, 'generator_heating' => true,
        'generator_schedule' => GeneratorSchedule::Daytime->value,
        'closed_territory' => true,
        'sector_match' => ['id' => 1, 'kind' => 'complex', 'name' => 'Житловий комплекс "Гулівер"'],
    ]);

    expect($caption)->toBe(<<<'TEXT'
        💵 <b>400 $ ≈ 18 000 грн</b>
        🏠 2 комн · 4/9 эт · 52 м²
        📍 Житловий комплекс &quot;Гулівер&quot; · вулиця Академіка Заболотного, 34

        ⚡ Генератор: ✅
        💧 Вода при отключениях: ✅
        🔥 Тепло при отключениях: ✅
        🛗 Лифт при отключениях: ❓
        ⏱ График генератора: весь день ✅
        🔒 Закрытая территория: ✅
        👤 Собственник: ❓

        <a href="https://www.olx.ua/d/uk/obyavlenie/zdam-2k-ID11oxAU.html">olx.ua — открыть объявление</a>
        TEXT);
});

it('shows a price in hryvnias once and unknown values as ❓', function () {
    $caption = card(['price' => 15000, 'currency' => CurrencyCode::Uah, 'price_uah' => 15000, 'rooms' => null, 'floors_total' => null, 'area' => null]);

    expect($caption)->toStartWith("💵 <b>15 000 грн</b>\n🏠 ? комн · 4 эт\n📍 Пересипський");
});

it('marks a district-only match with 📍?', function () {
    $listing = Listing::factory()->make(['url' => 'https://dom.ria.com/uk/realty-1.html', 'flags' => [Flag::DistrictOnly]]);
    $listing->enrichment = array_merge((new Enrichment)->toArray(), [
        'sector_match' => ['id' => 9, 'kind' => 'district', 'name' => 'Селище Котовського'],
    ]);

    expect(app(ListingCard::class)->caption($listing))->toContain('📍? только район: Селище Котовського')
        ->and(app(ListingCard::class)->caption($listing))->toContain('dom.ria.com — открыть объявление');
});

it('calls a one-room listing that passed as a euro two-room flat so', function () {
    expect(card(['rooms' => 1, 'flags' => [Flag::EuroTwoRoom]]))->toContain('🏠 евродвушка · 4/9 эт · 52 м²');
});

it('escapes text from the listing', function () {
    $caption = card([], ['street' => '<b>Бреуса</b> & Co']);

    expect($caption)->toContain('&lt;b&gt;Бреуса&lt;/b&gt; &amp; Co');
});

it('fits a Telegram photo caption', function () {
    expect(mb_strlen(strip_tags(card([], ['complex' => str_repeat('Довга назва ', 10)]))))->toBeLessThan(1024);
});

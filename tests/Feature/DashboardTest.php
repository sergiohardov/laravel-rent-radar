<?php

use App\Currency\ExchangeRates;
use App\Filament\Widgets\Funnel;
use App\Filament\Widgets\Latency;
use App\Filament\Widgets\LlmUsage;
use App\Filament\Widgets\RejectReasonsChart;
use App\Filament\Widgets\Service;
use App\Filament\Widgets\SourcesStatus;
use App\Models\Listing;
use App\Models\Source;
use App\Models\User;
use App\Pipeline\Decision;
use App\Sources\SourceName;
use Filament\Pages\Dashboard;
use Filament\Widgets\FilamentInfoWidget;
use Livewire\Livewire;
use Tests\Fakes\FakeExchangeRates;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->freezeSecond();
    app()->instance(ExchangeRates::class, new FakeExchangeRates(usd: 41.5, eur: 48.25));
});

it('puts every widget on the dashboard, and only them', function () {
    $this->get('/admin')->assertOk();

    Livewire::test(Dashboard::class)
        ->assertSeeLivewire(SourcesStatus::class)
        ->assertSeeLivewire(Funnel::class)
        ->assertSeeLivewire(Latency::class)
        ->assertSeeLivewire(LlmUsage::class)
        ->assertSeeLivewire(Service::class)
        ->assertSeeLivewire(RejectReasonsChart::class)
        ->assertDontSeeLivewire(FilamentInfoWidget::class);
});

it('tells a working, a failing, a switched off and a stalled source apart', function () {
    Source::for(SourceName::Lun)->update(['last_run_at' => now()->subMinutes(3), 'last_report' => ['created' => 5, 'passed' => 1, 'rejected' => 4, 'seconds' => 1.2]]);
    Source::for(SourceName::Olx)->update(['last_run_at' => now()->subMinutes(2), 'consecutive_failures' => 3]);
    Source::for(SourceName::DimRia)->update(['last_run_at' => now()->subHours(2)]);

    Livewire::test(SourcesStatus::class)
        ->assertSee(['работает', 'новых 5, прошли 1, отсеяно 4 · 1.2 с', 'ошибок подряд: 3', 'давно не запускалась']);

    Source::for(SourceName::DimRia)->update(['enabled' => false]);

    Livewire::test(SourcesStatus::class)->assertSee('выключена');
});

it('shows the funnel, the speed and the LLM spend', function () {
    Listing::factory()->create(['decision' => Decision::Notified, 'enriched_at' => now(), 'published_at' => now()->subMinutes(12), 'notified_at' => now()]);
    config(['services.anthropic.cost_per_call_usd' => 0.0054]);

    Livewire::test(Funnel::class)->assertSee(['Найдено', 'Отправлено']);
    Livewire::test(Latency::class)->assertSee('12 мин');
    Livewire::test(LlmUsage::class)->assertSee(['1 вызов', '≈ $0.01']);
});

it('shows the queue and the rates', function () {
    Livewire::test(Service::class)->assertSee(['В очереди', 'Упавшие задачи', '1 $ = 41,50 грн', '1 € = 48,25 грн', 'выключены']);
});

it('draws the reject reasons', function () {
    Listing::factory()->create(['decision' => Decision::Rejected, 'reject_reason' => 'floor 9 not in 1–6']);

    Livewire::test(RejectReasonsChart::class)->assertSee('Причины отсева за 7 дней');
});

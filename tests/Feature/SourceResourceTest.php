<?php

use App\Filament\Resources\Sources\Pages\ManageSources;
use App\Filament\Resources\Sources\SourceResource;
use App\Models\Source;
use App\Models\User;
use App\Sources\SourceName;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('lists every source with its run state, also before the first run', function () {
    Source::for(SourceName::Olx)->update([
        'last_run_at' => now(),
        'last_report' => ['created' => 3, 'passed' => 1, 'rejected' => 2, 'failed' => 0, 'seconds' => 3.4],
        'consecutive_failures' => 3,
        'last_error' => 'RequestException: HTTP request returned status code 403',
    ]);

    $this->get(ManageSources::getUrl())->assertOk()
        ->assertSee(['ЛУН', 'OLX', 'DIM.RIA', 'новых 3, прошли 1, отсеяно 2 · 3.4 с', 'status code 403']);

    expect(Source::query()->count())->toBe(3);
});

it('summarises the last run', function () {
    expect(SourceResource::reportSummary(null))->toBeNull()
        ->and(SourceResource::reportSummary(['created' => 5, 'passed' => 2, 'rejected' => 1, 'failed' => 2, 'seconds' => 12.1]))
        ->toBe('новых 5, прошли 2, отсеяно 1, сбой LLM 2 · 12.1 с');
});

it('switches a source off and the proxy on from the table', function () {
    $olx = Source::for(SourceName::Olx);

    Livewire::test(ManageSources::class)
        ->call('updateTableColumnState', 'enabled', (string) $olx->getKey(), false)
        ->call('updateTableColumnState', 'use_proxy', (string) $olx->getKey(), true);

    expect($olx->refresh()->enabled)->toBeFalse()
        ->and($olx->use_proxy)->toBeTrue();
});

it('edits the run interval', function () {
    $lun = Source::for(SourceName::Lun);

    Livewire::test(ManageSources::class)
        ->callAction(TestAction::make('edit')->table($lun), data: ['interval_min_minutes' => 7, 'interval_max_minutes' => 12])
        ->assertHasNoFormErrors();

    expect([$lun->refresh()->interval_min_minutes, $lun->interval_max_minutes])->toBe([7, 12]);
});

it('rejects an interval whose start is above its end', function () {
    $lun = Source::for(SourceName::Lun);

    Livewire::test(ManageSources::class)
        ->callAction(TestAction::make('edit')->table($lun), data: ['interval_min_minutes' => 15, 'interval_max_minutes' => 10])
        ->assertHasFormErrors(['interval_min_minutes' => 'lte']);
});

it('makes a source due now for the next scheduler tick', function () {
    $dimria = Source::for(SourceName::DimRia);
    $dimria->update(['next_run_at' => now()->addMinutes(8)]);

    Livewire::test(ManageSources::class)
        ->callAction(TestAction::make('runNow')->table($dimria))
        ->assertNotified('DIM.RIA запустится в течение минуты');

    expect($dimria->refresh()->isDue(now()->toImmutable()))->toBeTrue();
});

it('offers no way to create or delete a source', function () {
    Livewire::test(ManageSources::class)
        ->assertActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');
});

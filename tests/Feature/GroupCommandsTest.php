<?php

use App\Models\Listing;
use App\Models\Source;
use App\Pipeline\Decision;
use App\Sources\SourceName;
use App\Telegram\GroupReports;
use SergiX44\Nutgram\Nutgram;

const WORK_GROUP = -1004355960067;

beforeEach(function () {
    $this->freezeSecond();
    config(['services.telegram.chat_id' => (string) WORK_GROUP, 'nutgram.config.bot_name' => 'rents_radar_bot']);
});

/**
 * A command message as Telegram delivers it from a group.
 */
function groupCommand(string $command, int $chatId = WORK_GROUP): array
{
    return [
        'text' => $command,
        'entities' => [['type' => 'bot_command', 'offset' => 0, 'length' => mb_strlen($command)]],
        'chat' => ['id' => $chatId, 'type' => 'supergroup', 'title' => 'Rent Radar'],
    ];
}

it('reports the state of every source', function () {
    Source::for(SourceName::Lun)->update(['last_run_at' => now()->subMinutes(3), 'last_report' => ['created' => 5, 'passed' => 1, 'rejected' => 4, 'seconds' => 1.2]]);
    Source::for(SourceName::Olx)->update(['last_run_at' => now()->subMinutes(2), 'consecutive_failures' => 3]);
    Source::for(SourceName::DimRia)->update(['enabled' => false]);

    $text = app(GroupReports::class)->status();

    expect($text)->toStartWith('<b>Площадки</b>')
        ->toContain('✅ <b>ЛУН</b> — работает')
        ->toContain('новых 5, прошли 1, отсеяно 4 · 1.2 с')
        ->toContain('❌ <b>OLX</b> — ошибок подряд: 3')
        ->toContain('⏸ <b>DIM.RIA</b> — выключена');
});

it('reports the funnel and the speed', function () {
    Listing::factory()->create(['decision' => Decision::Rejected, 'rejected_by' => 'hard_filter', 'reject_reason' => 'floor 9 not in 1–6', 'published_at' => now()->subMinutes(20)]);
    Listing::factory()->create(['decision' => Decision::Notified, 'enriched_at' => now(), 'published_at' => now()->subMinutes(10), 'notified_at' => now()]);

    expect(app(GroupReports::class)->funnel())
        ->toContain("Найдено: 2\nПрошли фильтры: 1\nПрочитал LLM: 1\nВ секторе: 1\nОтправлено: 1")
        ->toContain('От публикации до бота: 15 мин')
        ->toContain('От публикации до карточки: 10 мин');
});

it('draws reject reasons as bars against the most frequent one', function () {
    foreach (range(1, 4) as $_) {
        Listing::factory()->create(['decision' => Decision::Rejected, 'reject_reason' => 'floor 9 not in 1–6']);
    }
    Listing::factory()->create(['decision' => Decision::Rejected, 'reject_reason' => 'price 20 000 UAH > 15 000 UAH']);

    expect(app(GroupReports::class)->reasons())
        ->toContain('▇▇▇▇▇▇▇▇▇▇ Этаж — 4')
        ->toContain("\n▇▇▇ Цена — 1");
});

it('says when nothing was rejected', function () {
    expect(app(GroupReports::class)->reasons())->toContain('Отсеянных объявлений нет.');
});

it('answers the commands in the working group', function (string $command, string $heading) {
    app(Nutgram::class)
        ->hearMessage(groupCommand($command))
        ->reply()
        ->assertReply('sendMessage', ['chat_id' => WORK_GROUP, 'parse_mode' => 'HTML'])
        ->assertRaw(fn ($request) => str_contains((string) $request->getBody(), $heading) || str_contains(urldecode((string) $request->getBody()), $heading));
})->with([
    ['/status', 'Площадки'],
    ['/funnel@rents_radar_bot', 'Воронка за 24 часа'],
    ['/reasons', 'Причины отсева за 7 дней'],
    ['/help', 'Команды'],
]);

it('keeps quiet in any other chat', function () {
    app(Nutgram::class)
        ->hearMessage(groupCommand('/status', -1009999999999))
        ->reply()
        ->assertNoReply();
});

it('still answers /ping anywhere, to find the chat id', function () {
    app(Nutgram::class)
        ->hearMessage(groupCommand('/ping', -1009999999999))
        ->reply()
        ->assertReplyText('pong · chat_id -1009999999999');
});

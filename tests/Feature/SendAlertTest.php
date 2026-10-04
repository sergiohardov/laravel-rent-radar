<?php

use App\Telegram\GroupMessageLimit;
use App\Telegram\NutgramTelegramNotifier;
use App\Telegram\SendAlert;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use Tests\Fakes\FakeTelegramNotifier;

it('posts the alert through the notifier', function () {
    $notifier = new FakeTelegramNotifier;

    (new SendAlert('<b>ЛУН</b> не отвечает'))->handle($notifier);

    expect($notifier->alerts)->toBe(['<b>ЛУН</b> не отвечает']);
});

it('waits for retry_after when Telegram says too many requests', function () {
    $job = Mockery::mock(SendAlert::class, ['text'])->makePartial();
    $job->shouldReceive('release')->once()->with(9);

    $job->handle(new FakeTelegramNotifier(new TelegramException('Too Many Requests', 429, parameters: ['retry_after' => 9])));
});

it('shares the group limit with listing cards as one message', function () {
    expect((new SendAlert('text'))->middleware())->toEqual([new GroupMessageLimit(1)]);
});

it('sends the alert as an HTML message without a link preview', function () {
    config(['services.telegram.chat_id' => '-1004355960067']);
    $bot = app(Nutgram::class);
    $bot->willReceive(['message_id' => 57, 'date' => 1, 'chat' => ['id' => -1004355960067, 'type' => 'supergroup']]);

    expect(app(NutgramTelegramNotifier::class)->alert('<b>OLX</b> снова работает'))->toBe(57);
    $bot->assertReply('sendMessage', [
        'chat_id' => '-1004355960067',
        'text' => '<b>OLX</b> снова работает',
        'parse_mode' => 'HTML',
    ]);
});

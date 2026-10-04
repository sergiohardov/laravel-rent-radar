<?php

use App\Currency\CurrencyCode;
use App\Enrichment\Enrichment;
use App\Enrichment\ListingEnricher;
use App\Models\Listing;
use App\Pipeline\Decision;
use App\Pipeline\Pipeline;
use App\Sources\SourceName;
use App\Telegram\GroupMessageLimit;
use App\Telegram\NutgramTelegramNotifier;
use App\Telegram\SendListingCard;
use App\Telegram\TelegramNotifier;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface as Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Testing\FormDataParser;
use Tests\Fakes\FakeListingEnricher;
use Tests\Fakes\FakeListingSource;
use Tests\Fakes\FakeTelegramNotifier;

it('queues the card of a listing that passed every stage', function () {
    Queue::fake();
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(generator: true, street: 'Ойстраха')));

    app(Pipeline::class)->run(FakeListingSource::withIds(1, 1, SourceName::Olx));

    $listing = Listing::query()->sole();
    Queue::assertPushed(SendListingCard::class, fn (SendListingCard $job) => $job->listingId === $listing->id);
    expect($listing->enrichment['street'])->toBe('Ойстраха');
});

it('does not queue a rejected listing', function () {
    Queue::fake();
    app()->instance(ListingEnricher::class, new FakeListingEnricher(new Enrichment(generator: false)));

    app(Pipeline::class)->run(FakeListingSource::withIds(1, 1, SourceName::Olx));

    Queue::assertNothingPushed();
});

it('sends the card and marks the listing notified', function () {
    $notifier = new FakeTelegramNotifier;
    app()->instance(TelegramNotifier::class, $notifier);
    $listing = Listing::factory()->create(['external_id' => '42']);

    (new SendListingCard($listing->id))->handle($notifier);

    $listing->refresh();
    expect($notifier->sent)->toBe(['42'])
        ->and($listing->decision)->toBe(Decision::Notified)
        ->and($listing->telegram_message_id)->toBe(100)
        ->and($listing->notified_at)->not->toBeNull();
});

it('never sends the same listing twice', function () {
    $notifier = new FakeTelegramNotifier;
    $listing = Listing::factory()->create(['telegram_message_id' => 7]);

    (new SendListingCard($listing->id))->handle($notifier);

    expect($notifier->sent)->toBe([]);
});

it('waits for retry_after when Telegram says too many requests', function () {
    $job = Mockery::mock(SendListingCard::class, [Listing::factory()->create()->id])->makePartial();
    $job->shouldReceive('release')->once()->with(17);

    $job->handle(new FakeTelegramNotifier(new TelegramException('Too Many Requests', 429, parameters: ['retry_after' => 17])));
});

it('counts every photo of the album against the group limit', function () {
    $album = Listing::factory()->create(['photo_urls' => array_map(fn ($i) => "https://cdn.example.test/{$i}.jpg", range(1, 7))]);
    $text = Listing::factory()->create(['photo_urls' => null]);

    expect((new SendListingCard($album->id))->middleware())->toEqual([new GroupMessageLimit(7)])
        ->and((new SendListingCard($text->id))->middleware())->toEqual([new GroupMessageLimit(1)]);
});

it('drains the queue every minute from the scheduler', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'queue:work'));

    expect($event->command)->toContain('--stop-when-empty')
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

/**
 * A message as Telegram returns it from the test group.
 */
function sentMessage(int $id): array
{
    return ['message_id' => $id, 'date' => 1, 'chat' => ['id' => -1004355960067, 'type' => 'supergroup']];
}

it('posts the photos as one album with the HTML caption on the first', function () {
    config(['services.telegram.chat_id' => '-1004355960067']);
    $bot = app(Nutgram::class);
    $bot->willReceive([sentMessage(60), sentMessage(61), sentMessage(62)]);
    $photos = ['https://cdn.example.test/1.jpg', 'https://cdn.example.test/2.jpg', 'https://cdn.example.test/3.jpg'];
    $listing = Listing::factory()->make(['photo_urls' => $photos, 'price' => 12000, 'currency' => CurrencyCode::Uah]);

    expect(app(NutgramTelegramNotifier::class)->send($listing))->toBe(60);

    $bot->assertReply('sendMediaGroup', ['chat_id' => '-1004355960067']);
    $bot->assertRaw(function (Request $request) use (&$media) {
        $media = json_decode(FormDataParser::parse($request)->params['media'], true);

        return true;
    }, index: 0);
    expect(array_column($media, 'media'))->toBe($photos)
        ->and($media[0]['caption'])->toContain('12 000 грн')
        ->and($media[0]['parse_mode'])->toBe('HTML')
        ->and($media[1])->not->toHaveKey('caption');
});

it('posts a single photo with the HTML caption to the group', function () {
    config(['services.telegram.chat_id' => '-1004355960067']);
    $bot = app(Nutgram::class);
    $bot->willReceive(sentMessage(55));
    $listing = Listing::factory()->make(['photo_urls' => ['https://cdn.riastatic.com/photos/x/1fl.jpg'], 'price' => 12000, 'currency' => CurrencyCode::Uah]);

    $messageId = app(NutgramTelegramNotifier::class)->send($listing);

    expect($messageId)->toBe(55);
    $bot->assertReply('sendPhoto', ['chat_id' => '-1004355960067', 'photo' => 'https://cdn.riastatic.com/photos/x/1fl.jpg', 'parse_mode' => 'HTML']);
});

it('falls back to a text message when Telegram cannot fetch the photo', function () {
    config(['services.telegram.chat_id' => '-1004355960067']);
    $bot = app(Nutgram::class);
    $bot->willReceive(['description' => 'Bad Request: wrong file identifier/HTTP URL specified', 'error_code' => 400], ok: false)
        ->willReceive(['message_id' => 56, 'date' => 1, 'chat' => ['id' => -1004355960067, 'type' => 'supergroup']]);
    $listing = Listing::factory()->make(['photo_urls' => ['https://example.test/broken.jpg']]);

    expect(app(NutgramTelegramNotifier::class)->send($listing))->toBe(56);
    $bot->assertReply('sendMessage', ['chat_id' => '-1004355960067', 'parse_mode' => 'HTML'], index: 1);
});

it('falls back to the main photo alone when Telegram cannot fetch one of the album', function () {
    config(['services.telegram.chat_id' => '-1004355960067']);
    $bot = app(Nutgram::class);
    $bot->willReceive(['description' => 'Bad Request: failed to get HTTP URL content', 'error_code' => 400], ok: false)
        ->willReceive(sentMessage(57));
    $listing = Listing::factory()->make(['photo_urls' => ['https://cdn.example.test/1.jpg', 'https://example.test/broken.jpg']]);

    expect(app(NutgramTelegramNotifier::class)->send($listing))->toBe(57);
    $bot->assertReply('sendPhoto', ['photo' => 'https://cdn.example.test/1.jpg', 'parse_mode' => 'HTML'], index: 1);
});

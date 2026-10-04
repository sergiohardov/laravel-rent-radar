{{-- Telegram HTML caption of a listing; built by App\Telegram\ListingCard --}}
💵 <b>{{ $price ?? 'цена ❓' }}</b>
🏠 {{ $params }}
@if ($place)
{{ $districtOnly ? '📍? только район:' : '📍' }} {{ $place }}
@endif

@foreach ($marks as $label => $mark)
{{ $label }}: {{ $mark }}
@endforeach

<a href="{{ $url }}">{{ $site }} — открыть объявление</a>

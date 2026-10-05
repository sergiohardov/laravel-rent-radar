{{-- /status in the group; built by App\Telegram\GroupReports --}}
<b>Площадки</b>
@foreach ($sources as $source)
{{ $source['emoji'] }} <b>{{ $source['name'] }}</b> — {{ $source['state'] }}
@if ($source['details'] !== '')
<i>{{ $source['details'] }}</i>
@endif
@endforeach

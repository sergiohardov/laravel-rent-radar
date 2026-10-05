{{-- /reasons in the group; built by App\Telegram\GroupReports --}}
<b>Причины отсева за 7 дней</b>
@forelse ($rows as $row)
{{ $row['bar'] }} {{ $row['label'] }} — {{ $row['count'] }}
@empty
Отсеянных объявлений нет.
@endforelse

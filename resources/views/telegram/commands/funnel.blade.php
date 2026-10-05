{{-- /funnel in the group; built by App\Telegram\GroupReports --}}
<b>Воронка за 24 часа</b>
Найдено: {{ $funnel['found'] }}
Прошли фильтры: {{ $funnel['passed_fields'] }}
Прочитал LLM: {{ $funnel['enriched'] }}
В секторе: {{ $funnel['in_sector'] }}
Отправлено: {{ $funnel['sent'] }}

<b>Скорость</b>, медиана
От публикации до бота: {{ $detection }}
От публикации до карточки: {{ $card }}

{{-- Telegram HTML alert; sent by App\Sources\SourceAlerts --}}
🔇 <b>{{ $source }}</b>: нет новых объявлений {{ $hours }} ч
Последнее новое: {{ $lastNew ?? 'не было' }}
Последний запуск: {{ $lastRun }}
@if ($failures > 0)
Ошибок подряд — {{ $failures }}: <code>{{ $error }}</code>
@endif

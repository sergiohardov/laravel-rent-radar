{{-- Telegram HTML alert; sent by App\Sources\SourceAlerts --}}
⚠️ <b>{{ $source }}</b> не работает: ошибок подряд — {{ $failures }}
<code>{{ $error }}</code>
Последний успешный запуск: {{ $lastSuccess ?? 'не было' }}

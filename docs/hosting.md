# Хостинг nic.ua

Факты о проде, проверенные на месте. Решения о проде — в [CLAUDE.md](../CLAUDE.md) → «Прод».

## Проверка 2026-10-04

Одноразовый `check.php`, загруженный через File Manager в cPanel и открытый в браузере. Делает те же запросы, что будет делать бот: PHP curl, HTTP/1.1, `User-Agent: GuzzleHttp/7`. SSH в cPanel нет: пункт «SSH Access» не показывается, по справке nic.ua его открывает поддержка.

### Площадки с IP хостинга

Исходящий IP — `95.216.242.245`, AS24940 Hetzner Online, Финляндия.

| Площадка | С хостинга | С украинского домашнего IP |
|---|---|---|
| ЛУН `flatfy.ua/api/realties` | **403**, проверка Cloudflare «Just a moment…» | 200 |
| OLX `olx.ua/api/v1/offers/` | **403** от CloudFront и по HTTP/1.1, и по HTTP/2 | 200 по HTTP/1.1, 403 по HTTP/2 |
| DIM.RIA `searchEngine` + `realty/data` | 200 | 200 |

Чтобы понять причину блокировки, ЛУН и OLX проверили через Scrape.do (бесплатный план) с украинских IP разных типов:

| Тип IP | Сеть | ЛУН | OLX |
|---|---|---|---|
| Дата-центр, UA | AS200525 Hosting Ukraine LTD | 200 | 200 |
| Домашний, UA | AS15713 Global-City-Net | 200 | 200 |

Украинский IP дата-центра проходит, значит, блокируют по стране или конкретно сеть Hetzner, а не любые дата-центры.

**Решение (2026-10-04):** хостинг nic.ua остаётся. ЛУН и OLX ходят через украинский прокси дата-центра (порядка $1–3 в месяц), DIM.RIA — напрямую. Прокси покупаем перед деплоем (пункт 29) и подтверждаем с хостинга через `check.php`. Оговорка: Scrape.do мог повторять запросы и подставлять свои заголовки, поэтому окончательная проверка — с купленным прокси.

### PHP

| Что | Значение |
|---|---|
| Веб-сервер | **LiteSpeed** (`sapi=litespeed`, `lsphp`), а не Apache; `.htaccess` LiteSpeed понимает |
| PHP для сайта по умолчанию | 8.3.35 — для домена бота переключить на 8.4 в cPanel → Select PHP Version |
| `php` в shell | `/usr/local/bin/php` → 8.3.35 |
| PHP 8.4 по полному пути | `/opt/alt/php84/usr/bin/php` есть (а также `php85` и старые версии) |
| `memory_limit` | 128M |
| `max_execution_time` | 55 с (скрипт сам выставил `set_time_limit(55)`; лимит хостинга — проверить отдельно) |
| `date.timezone` | `Europe/Kiev` — приложение работает в UTC через `config/app.php` |
| Расширения | все нужные есть (`pdo_pgsql`, `intl`, `zip`, `bcmath`, `mbstring`…); нет `opcache` |
| `exec`, `shell_exec`, `proc_open`, `symlink` | разрешены |
| curl | 8.22.0, OpenSSL 1.1.1w, HTTP/2 поддерживается |

Вывод для cron и деплоя: системный `php` — это 8.3, поэтому вызываем `/opt/alt/php84/usr/bin/php` по полному пути, как и записано в CLAUDE.md.

### Ещё проверить (пункт 28)

- Версия PostgreSQL: `select version();` в phpPgAdmin.
- Минимальный шаг cron в cPanel.
- Почему нет `opcache` для сайта — возможно, включается в Select PHP Version → Extensions.
- Открыть SSH через поддержку nic.ua: нужен для деплоя через rsync (пункт 30).

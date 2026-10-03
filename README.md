# laravel-rent-radar

Личный Telegram-бот для поиска квартиры в долгосрочную аренду в Одессе. Опрашивает площадки объявлений, отбирает подходящие по фильтрам и сектору на карте и присылает карточки в Telegram-группу.

## Как работает

1. Каждые 5–10 минут бот забирает новые объявления с ЛУН, OLX и DIM.RIA.
2. Отсекает неподходящие по жёстким фильтрам: комнаты, этаж, цена.
3. Проверяет сектор: точка + радиус на карте или ЖК/улица из whitelist в тексте объявления.
4. LLM извлекает из заголовка и описания адрес, этаж и флаги: генератор, закрытая территория, собственник.
5. Подходящие объявления приходят карточкой в группу. Кнопка «🗑 убрать» убирает лишнее.

Настройки фильтров, whitelist локаций, источники и журнал объявлений — в админке на Filament.

## Стек

Laravel 13 · Filament 5 · PHP 8.4 · PostgreSQL · Pest · Nutgram · Claude API (Haiku) · Docker

## Статус

В разработке. План — в [docs/ROADMAP.md](docs/ROADMAP.md), текущее положение — в [docs/LOG.md](docs/LOG.md).

## Запуск

Локально всё работает в Docker, на машине нужны Docker и `git`. Полная инструкция появится после этапа «Окружение».

Первый запуск:

```bash
cp .env.example .env
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan test    # Pest, база rent_radar_test, сеть закрыта
```

Дальше:

```bash
docker compose up -d                        # php (http://localhost:8080), scheduler, postgres
docker compose --profile tunnel up -d       # + публичный HTTPS-туннель для webhook dev-бота
docker compose run --rm node npm run build  # сборка ассетов
docker compose down                         # остановить; данные БД остаются в volume pgdata
```

| Сервис | Что делает |
|---|---|
| `php` | PHP 8.4 + Apache с лимитами хостинга (128M, 60 с), код монтируется из папки проекта |
| `scheduler` | тот же образ, supercronic раз в минуту запускает `artisan schedule:run` — как cron на хостинге |
| `postgres` | PostgreSQL 16, базы `rent_radar` и `rent_radar_test`; с хоста — порт 5433 |
| `node` | Node 24 для сборки ассетов, запускается разово |
| `cloudflared` | быстрый туннель без аккаунта Cloudflare; адрес меняется при каждом запуске |

Для работы понадобятся:

- токен Telegram-бота и id группы (для разработки — отдельный бот и тестовая группа);
- ключ Claude API.

Все переменные будут описаны в `.env.example`.

## Прод

Shared-хостинг nic.ua (cPanel, Apache, PHP 8.4, PostgreSQL). Cron раз в минуту запускает Laravel scheduler. Деплой — GitHub Actions: тесты и сборка в CI, на хостинг — rsync и миграции.

## Документация

- [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md) — требования к квартире
- [docs/ROADMAP.md](docs/ROADMAP.md) — план развития проекта
- [docs/sources/](docs/sources/) — разведка площадок: как получать данные, поля, риски
- [docs/hosting.md](docs/hosting.md) — проверенные факты о хостинге nic.ua
- [docs/LOG.md](docs/LOG.md) — журнал изменений и текущий статус
- [CLAUDE.md](CLAUDE.md) — принятые решения и правила работы с Claude

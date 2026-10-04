# laravel-rent-radar

Личный Telegram-бот для поиска квартиры в долгосрочную аренду в Одессе. Опрашивает площадки объявлений, отбирает подходящие по фильтрам и сектору на карте и присылает карточки в Telegram-группу.

## Как работает

1. Каждые 5–10 минут бот забирает новые объявления с ЛУН, OLX и DIM.RIA.
2. Отсекает неподходящие по жёстким фильтрам: комнаты, этаж, цена.
3. Проверяет сектор: точка + радиус на карте или ЖК/улица из whitelist в тексте объявления.
4. LLM извлекает из заголовка и описания адрес, этаж и флаги: генератор, закрытая территория, собственник.
5. Подходящие объявления приходят карточкой в группу.

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
docker compose exec php php artisan migrate --seed  # + админ из ADMIN_EMAIL / ADMIN_PASSWORD
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
| `scheduler` | тот же образ, supercronic раз в минуту запускает `artisan schedule:run` — как cron на хостинге: опрос площадок, повтор `failed`, проверка «сутки без новых объявлений», очередь карточек и алертов (`queue:work --stop-when-empty`) |
| `postgres` | PostgreSQL 10, как на хостинге; базы `rent_radar` и `rent_radar_test`; с хоста — порт 5433 |
| `node` | Node 24 для сборки ассетов, запускается разово |
| `cloudflared` | быстрый туннель без аккаунта Cloudflare; адрес меняется при каждом запуске |

Для работы понадобятся:

- токен Telegram-бота и id группы (для разработки — отдельный бот и тестовая группа);
- ключ Claude API.

Все переменные будут описаны в `.env.example`.

Telegram dev-бота:

1. Бот от @BotFather и тестовая группа с ботом-администратором (право удалять сообщения).
2. В `.env`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (без `@`).
3. Туннель и webhook (адрес туннеля меняется при каждом запуске — команду повторять):
   ```bash
   docker compose --profile tunnel up -d && docker compose exec php php artisan telegram:set-webhook
   ```
4. В группе `/ping@<имя_бота>` — бот ответит `chat_id`, его в `TELEGRAM_CHAT_ID`.

Админка: http://localhost:8080/admin, вход — `ADMIN_EMAIL` и `ADMIN_PASSWORD` из `.env`. На проде админ создаётся командой `php artisan make:filament-user`, пароль вводит владелец. Разделы: «Настройки» — цена, комнаты, этажи, переключатели ❓ и точка сектора, новые значения действуют со следующего запуска источника; «Сектор» — whitelist улиц, ЖК и районов с алиасами и кнопка импорта из OpenStreetMap; «Источники» — вкл/выкл, прокси, интервал, состояние запусков и «Запустить сейчас»; «Журнал» — все объявления с решением, стадией и причиной отсева и тем, что прочитал LLM.

## Прод

Shared-хостинг nic.ua (cPanel, LiteSpeed, PHP 8.4, PostgreSQL 10). Cron раз в минуту запускает Laravel scheduler. Деплой — GitHub Actions: тесты и сборка в CI, на хостинг — rsync и миграции. Факты о хостинге — [docs/hosting.md](docs/hosting.md).

Подготовка хостинга (один раз):

1. **Поддомен** `rentradar.creastep.com` в cPanel → Domains, корень — `/home/<логин>/rentradar.creastep.com/public` (приложение лежит в `~/rentradar.creastep.com`, наружу смотрит только `public/`).
2. **SSL** — cPanel → SSL/TLS Status → AutoSSL для поддомена.
3. **PHP 8.4** — cPanel → Select PHP Version, расширения `pdo_pgsql intl zip bcmath opcache`.
4. **База** — cPanel → PostgreSQL Databases: база и пользователь со всеми правами на неё.
5. **`.env`** — `~/rentradar.creastep.com/.env` по шаблону [.env.production.example](.env.production.example): ключи и пароли только там (`chmod 600`). У прода свой бот и рабочая группа, у dev — свой бот и тестовая группа. Конфиг на проде закеширован: после правки `.env` — `/opt/alt/php84/usr/bin/php artisan optimize`.
6. **Ключ для деплоя** — отдельный SSH-ключ без пароля, авторизованный на хостинге (`ssh-copy-id -p 2345`).
7. **Секреты GitHub** — Settings → Secrets and variables → Actions: `SSH_HOST`, `SSH_PORT` (2345), `SSH_USER`, `SSH_PRIVATE_KEY` (закрытый ключ целиком), `SSH_KNOWN_HOSTS` (вывод `ssh-keyscan -p 2345 <сервер>`).

Деплой — [.github/workflows/deploy.yml](.github/workflows/deploy.yml): каждый PR проверяется тестами и Pint на PostgreSQL 10; пуш в `main` после тестов ставит зависимости без dev, заливает приложение rsync по SSH (что не заливается и не удаляется на хостинге — [.deployignore](.deployignore)), выполняет миграции и кеширует конфиг, маршруты и представления. Повторить вручную — Actions → «Tests and deploy» → Run workflow.

## Документация

- [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md) — требования к квартире
- [docs/ROADMAP.md](docs/ROADMAP.md) — план развития проекта
- [docs/sources/](docs/sources/) — разведка площадок: как получать данные, поля, риски
- [docs/hosting.md](docs/hosting.md) — проверенные факты о хостинге nic.ua
- [docs/LOG.md](docs/LOG.md) — журнал изменений и текущий статус
- [CLAUDE.md](CLAUDE.md) — принятые решения и правила работы с Claude

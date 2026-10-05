# Полезные команды

Слева — что нужно получить, справа — команда. Dev-команды — из папки проекта на Mac, прод — по SSH на хостинге nic.ua в `~/rentradar.creastep.com`. В командах прода PHP — по полному пути `/opt/alt/php84/usr/bin/php` (обычный `php` зависит от выбора версии в панели). `КЛЮЧ`, `ЛОГИН`, `СЕРВЕР` — подставить свои; ключи и пароли в чат не присылать, только строки результата.

## Dev: окружение

| Что хочу | Команда |
|---|---|
| Запустить бота локально (php, планировщик, база) | `docker compose up -d` |
| Остановить бота, не тратить LLM | `docker compose stop` |
| Поднять туннель для webhook dev-бота | `docker compose --profile tunnel up -d` |
| Посмотреть, что делает планировщик | `docker compose logs scheduler --since 10m` |
| Пересобрать образ PHP (после правки Dockerfile) | `docker compose build php && docker compose up -d` |
| Открыть shell в контейнере | `docker compose exec php bash` |
| Пересоздать базу dev с нуля (данные пропадут) | `docker compose exec php php artisan migrate:fresh --seed --force` |
| Применить новые миграции | `docker compose exec php php artisan migrate` |
| Посмотреть таблицы и размер базы | `docker compose exec php php artisan db:show --counts` |
| Сбросить кеши Laravel в dev | `docker compose exec php php artisan optimize:clear` |

## Тесты и стиль

| Что хочу | Команда |
|---|---|
| Прогнать все тесты | `docker compose exec php php artisan test --compact` |
| Прогнать один файл тестов | `docker compose exec php php artisan test --compact tests/Feature/HardFilterTest.php` |
| Поправить стиль изменённых файлов | `docker compose exec php vendor/bin/pint --dirty` |
| Проверить стиль всего проекта (как в CI) | `docker compose exec php vendor/bin/pint --test` |
| Проверить workflow GitHub Actions | `docker run --rm -v "$PWD:/repo" --workdir /repo rhysd/actionlint:latest .github/workflows/deploy.yml` |

## Площадки, сектор, LLM

| Что хочу | Команда |
|---|---|
| Опросить одну площадку сейчас | `docker compose exec php php artisan sources:run lun` (`olx`, `dimria`) |
| Запустить самую просроченную площадку, как cron | `docker compose exec php php artisan sources:run-due` |
| Повторить LLM для объявлений со «Сбоем» | `docker compose exec php php artisan listings:retry-failed` |
| Проверить, не молчит ли площадка сутки (алерт) | `docker compose exec php php artisan sources:check-silence` |
| Импортировать сектор из OpenStreetMap | `docker compose exec php php artisan locations:import-osm --lat=46.579410 --lng=30.804915 --radius=1000` |
| Проверить качество LLM на размеченном наборе (~$0.17) | `docker compose exec php php artisan llm:eval` |

## Telegram

| Что хочу | Команда |
|---|---|
| Направить webhook dev-бота на туннель (после перезапуска туннеля) | `docker compose exec php php artisan telegram:set-webhook` |
| Перезапустить туннель, если адрес протух | `docker compose --profile tunnel restart cloudflared` |
| Узнать `chat_id` группы | в группе: `/ping@имя_бота` |
| Отправить проверочное сообщение в группу (dev) | `docker compose exec php php artisan tinker --execute 'app(App\Telegram\TelegramNotifier::class)->alert("Проверка");'` |

## Прод: SSH и приложение

| Что хочу | Команда |
|---|---|
| Зайти на хостинг | `ssh -p 2345 ЛОГИН@СЕРВЕР` |
| Применить правку `.env` (конфиг закеширован) | `/opt/alt/php84/usr/bin/php artisan optimize` |
| Проверить, к какой базе подключён прод | `/opt/alt/php84/usr/bin/php artisan tinker --execute 'echo config("database.default"), " / ", config("database.connections.".config("database.default").".database"), PHP_EOL;'` |
| Посмотреть строки `DB_` в `.env` без пароля | `grep -n "^DB_" .env \| sed 's/PASSWORD=.*/PASSWORD=***/'` |
| Таблицы и число строк в базе прода | `/opt/alt/php84/usr/bin/php artisan db:show --counts` |
| Создать админа | `/opt/alt/php84/usr/bin/php artisan make:filament-user` |
| Поставить webhook прод-бота | `/opt/alt/php84/usr/bin/php artisan telegram:set-webhook` |
| Отправить проверочное сообщение в группу (прод) | `/opt/alt/php84/usr/bin/php artisan tinker --execute 'app(App\Telegram\TelegramNotifier::class)->alert("Проверка");'` |
| Запустить планировщик вручную и увидеть ошибки | `/opt/alt/php84/usr/bin/php artisan schedule:run` |
| Посмотреть активные задачи cron | `crontab -l` |
| Последние ошибки Laravel за сегодня | `tail -n 50 storage/logs/laravel-$(date +%F).log` |
| Модули PHP 8.4 на хостинге | `/opt/alt/php84/usr/bin/php -m` |

Строка cron в cPanel («Once Per Minute»): `cd /home/ЛОГИН/rentradar.creastep.com && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1`.

## Деплой

| Что хочу | Команда |
|---|---|
| Задеплоить | смержить PR в `main` — GitHub Actions сам прогонит тесты и зальёт |
| Повторить упавший деплой | GitHub → Actions → упавший запуск → Re-run failed jobs |
| Создать ключ для деплоя | `ssh-keygen -t ed25519 -f ~/.ssh/rentradar_deploy -N "" -C "github-actions rentradar"` |
| Авторизовать ключ на хостинге | `ssh-copy-id -i ~/.ssh/rentradar_deploy.pub -p 2345 ЛОГИН@СЕРВЕР` |
| Получить значение секрета `SSH_KNOWN_HOSTS` | `ssh-keyscan -p 2345 СЕРВЕР` |
| Получить новый `APP_KEY` | `docker compose exec php php artisan key:generate --show` |
| Залить файл на хостинг | `scp -P 2345 ФАЙЛ ЛОГИН@СЕРВЕР:rentradar.creastep.com/` |

## Ретранслятор (CityHost)

| Что хочу | Команда |
|---|---|
| Создать новый ключ ретранслятора (в папке сайта `relay.creastep.com`) | `KEY=$(php -r 'echo bin2hex(random_bytes(32));'); printf "<?php\n\nreturn ['key' => '%s'];\n" "$KEY" > relay-config.php; echo "$KEY"` |
| Проверить, что ретранслятор видит ключ (64) | `php -r '$c = require "relay-config.php"; echo strlen($c["key"]), PHP_EOL;'` |
| Проверить, что ретранслятор жив (без ключа — 403) | `curl -s -o /dev/null -w "%{http_code}\n" https://relay.creastep.com/relay.php` |
| Проверить ЛУН через ретранслятор (200) | `curl -s -o /dev/null -w "lun: %{http_code} %{time_total}s\n" "https://relay.creastep.com/relay.php?key=КЛЮЧ&url=https%3A%2F%2Fflatfy.ua%2Fapi%2Frealties%3Fgeo_id%3D10016589%26section_id%3D2%26page%3D1"` |
| Проверить, пускают ли ЛУН и OLX новый хостинг | залить [relay/check.php](../relay/check.php), выполнить `php check.php`, удалить файл |

## Сеть и DNS

| Что хочу | Команда |
|---|---|
| Узнать IP домена по публичному DNS | `dig +short A relay.creastep.com @1.1.1.1` |
| Сбросить кеш DNS на Mac | `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder` |
| Обратиться к серверу по IP в обход DNS | `curl -s --resolve relay.creastep.com:443:176.111.33.20 https://relay.creastep.com/relay.php` |
| Посмотреть сертификат сайта | `echo \| openssl s_client -connect relay.creastep.com:443 -servername relay.creastep.com 2>/dev/null \| openssl x509 -noout -subject -enddate` |
| Узнать, выпускает ли хостинг наружу порт (на хостинге) | `curl -sS -m 15 -o /dev/null -w "%{http_code}\n" http://portquiz.net:8080/` |

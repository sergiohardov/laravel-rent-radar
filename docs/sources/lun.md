# ЛУН (flatfy.ua)

Разведка 2026-10-04, пункт 01. Фикстура — [tests/Fixtures/lun/realties-odesa-rent-page-1.json](../../tests/Fixtures/lun/realties-odesa-rent-page-1.json).

## Итог

- Данные отдаёт JSON-эндпоинт, которым пользуется сам сайт. Headless-браузер не нужен.
- Запрос через `curl` без cookies и без особого User-Agent с украинского IP → 200, ~210 КБ, ~0,3 с. Перед сайтом стоит Cloudflare, лимитов в заголовках нет.
- ЛУН уже собирает OLX, rieltor.ua и DOM.RIA: в выборке из 120 свежих объявлений OLX — 64%, rieltor.ua — 27%, DOM.RIA — 9%.

## Запрос

```
GET https://flatfy.ua/api/realties?geo_id=10016589&section_id=2&lang=uk&sort=insert_time&page=1
```

| Параметр | Значение |
|---|---|
| `geo_id` | `10016589` — Одеса |
| `section_id` | `2` — долгосрочная аренда квартир |
| `sort` | `insert_time` — новые сверху; по умолчанию `relevance` |
| `page` | с 1, по 24 объявления на страницу |
| `group_collapse` | `1` — склеить дубли между сайтами; не передаём, см. «Дубли» |

Фильтры на стороне ЛУН тоже работают (`room_count=2`, `price_max=15000&currency=UAH`, `floor_min`, `floor_max`), но мы их **не передаём**: отбор делает `HardFilter`, и каждый отсев попадает в журнал. Объём небольшой (~120 объявлений за 9,5 вечерних часов, ~300–500 в сутки), так что за запуск обычно хватает первой страницы. Пагинация — до первого уже виденного объявления или до лимита страниц.

Ответ: `{data: [...], page, total_data_len, total_objects_count}`. `total_data_len` ограничен 10 000 — для чтения свежих страниц это не мешает.

Запасной путь, если API закроют: та же страница `https://flatfy.ua/uk/оренда-квартир-одеса?sort=insert_time` (~630 КБ HTML) содержит те же данные в `window.INITIAL_STATE`. Это JS-литерал, а не JSON: внутри встречается `undefined`, его надо заменить на `null`.

## Поля объявления → `RawListing`

| Поле ЛУН | Что это | Заметки |
|---|---|---|
| `id` | id объявления в ЛУН | `external_id` |
| `url_raw` | ссылка на первоисточник (olx.ua, rieltor.ua, dom.ria.com) | ссылка в карточке |
| `site.name` | площадка-первоисточник | показываем в карточке |
| `insert_time` | время публикации, ISO 8601 UTC | **обновляется при поднятии объявления**, поэтому старые объявления снова попадают наверх; повтор отсекает `SeenFilter` |
| `download_time` | когда ЛУН забрал объявление | |
| `price`, `currency` | целое число, `UAH` или `USD` | в выборке 80% UAH |
| `room_count`, `floor`, `floor_count` | int | в выборке не пустые ни разу |
| `area_total`, `area_kitchen`, `area_living` | float, м² | кухня — для будущего требования «кухня не маленькая» |
| `location` | `[lon, lat]` | **сначала долгота**; пусто у 15% |
| `geo_entities[]` | `type`: `district`, `microdistrict`, `street`, `house`, `residential_complex` | улица — у 76%, ЖК — у 36%; готовые кандидаты для `LocationMatcher` |
| `header`, `text` | заголовок и описание | вход для LLM |
| `images[].image_id` | число или `offers/<hash>` | `https://market-images.lunstatic.net/lun-ua/{w}/{h}/images/{image_id}.jpg` (есть `.webp`); оригинал — `https://storage.googleapis.com/lun-ua/images/{image_id}.jpg` |
| `is_owner` | bool | 👤 |
| `autonomy_power`, `autonomy_water`, `autonomy_heat`, `autonomy_lift`, `autonomy_net` | `true/false/null` | ЛУН сам извлекает, что работает при отключениях; заполнено примерно у половины |
| `heating_system_name` | `centralized`, `autonomous`, `individual`, `null` | |
| `pets_allowed_full`, `children_allowed` | | на будущее |
| `group_id`, `has_duplicates` | группа одного объекта на разных сайтах | сохраняем для будущей склейки дублей |

## Наблюдения и риски

- **Числа ЛУН заполняет сам и может ошибаться.** Пример: в поле `price` 22000, а в тексте «25000грн». Поэтому LLM и повторный `HardFilter` по тексту остаются нужны.
- **Неофициальный API** без гарантий: формат может поменяться без предупреждения. Ловим через фикстуры и алерт «3 ошибки подряд».
- **Cloudflare:** пропускает `curl` с украинского IP. Ответ на IP хостинга (Hetzner, Финляндия) проверяем в пункте 04.
- **Задержка:** самое свежее объявление появилось в выдаче за ~6 минут до запроса — ЛУН подхватывает быстро.

## Что это меняет для других пунктов

- **02 OLX и 03 DIM.RIA:** ЛУН их уже покрывает. Проверить полноту и задержку: все ли свежие объявления OLX и DIM.RIA по Одессе есть в ЛУН и насколько позже. Если покрытие полное, прямые адаптеры можно отложить.
- **16–17 LLM:** `autonomy_*` дают воду, тепло и лифт ещё до LLM. Предложение: объединять с ответом модели — если модель вернула `null`, брать значение ЛУН. Решаем в пункте 16.
- **18 LocationMatcher:** `geo_entities` с улицей и ЖК дополняют fuzzy match по тексту.

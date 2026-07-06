# Ревью пакета filament-topbar-menu — подтверждённые проблемы

Дата ревью: 2026-07-06. Multi-agent ревью (4 измерения) + адверсариальная верификация каждой находки
против реального vendor-кода Filament v5.6.8 / Laravel 13.18. Все 10 проблем **подтверждены** трассировкой
исходников. Тесты пакета (31/70 assertions) проходят, но перечисленное ниже они не покрывают.

**Окружение:** PHP/Composer на машине НЕТ. Тесты гонять только через Docker (OrbStack: `open -a OrbStack`):

```bash
docker run --rm -v "$PWD:/app" -w /app php84-intl php vendor/bin/phpunit
```

Статус: ничего из списка ещё НЕ исправлено. Порядок ниже — рекомендуемый порядок исправления.

---

## MAJOR

### 1. Dropdown никогда не виден — clipping из-за `overflow-x: auto`

- **Файл:** `resources/dist/filament-topbar-menu.css:6`
- **Причина:** у `.ftm-nav` задано `overflow-x: auto; overflow-y: visible`. По CSS Overflow §3.1 `visible`
  в паре с `auto` вычисляется как `auto` → nav становится scroll-контейнером высотой ~2rem и обрезает
  абсолютно позиционированный `.ftm-dropdown` (`top: 100%` + `margin-top: .375rem` — целиком за пределами бокса).
  `scrollbar-width: none` скрывает даже намёк на скролл: при hover/click не появляется ничего.
- **Эффект:** выпадающие меню (центральная фича) не работают нигде.
- **Fix:** убрать `overflow-x: auto` (и `scrollbar-width`/`::-webkit-scrollbar` правила) с `.ftm-nav`;
  переполнение решать иначе (например, `flex-wrap` не нужен — просто дать пунктам сжиматься, или ограничить
  количество). Альтернатива — рендерить dropdown через Alpine anchor/teleport, но это сложнее; проще убрать скролл.
- **Тест:** CSS статикой не проверить; минимум — убрать мёртвые правила и зафиксировать в комментарии.

### 2. Меню полностью скрыто ниже 1024px (мобильные/планшеты)

- **Файлы:** `src/TopbarMenuPlugin.php:16` (хук по умолчанию), `resources/dist/filament-topbar-menu.css:168-180` (мёртвый код)
- **Причина:** `TOPBAR_LOGO_AFTER` рендерится внутри `<div class="fi-topbar-start">`
  (vendor/filament/filament/resources/views/livewire/topbar.blade.php:42,106), а vendor
  `topbar.css:29-30` даёт этому контейнеру `hidden ... lg:flex` → ниже 1024px весь блок `display:none`.
  Наш `@media (max-width: 768px)` блок никогда не срабатывает (предок скрыт), README при этом обещает mobile-friendly.
- **Fix (честный вариант):**
  1) удалить мёртвый media-блок из CSS;
  2) в README задокументировать, что при дефолтном хуке меню видно от `lg` (это поведение Filament topbar);
  3) опционально: добавить CSS `@media (max-width:1023px) { .fi-topbar-start:has(.ftm-nav) { display:flex } }`
     ИЛИ рекомендовать альтернативный хук (например `SIDEBAR_NAV_START`) для мобильных — решить при реализации,
     не переопределяя layout агрессивно.

### 3. Drag-and-drop сортировка не сбрасывает кэш меню

- **Файлы:** `src/Filament/Resources/TopbarMenuItemResource.php:265` (`->reorderable('sort')`),
  `src/Models/TopbarMenuItem.php:61-67` (flush только на saved/deleted)
- **Причина:** Filament v5 `CanReorderRecords::reorderTable()`
  (vendor/filament/tables/src/Concerns/CanReorderRecords.php:44-55) делает один bulk
  `UPDATE ... CASE` через query builder → Eloquent-события не срабатывают → `TopbarMenu::flushCache()`
  не вызывается. Топбар держит старый порядок до `cache_ttl` (3600s). README (раздел Caching) и
  docblock в `config/filament-topbar-menu.php:21-24` обещают сброс при reorder — сейчас это ложь.
- **Fix:** в table() добавить `->afterReordering(fn () => app(\Vaslv\FilamentTopbarMenu\TopbarMenu::class)->flushCache())`
  (проверить точное имя хука в vendor/filament/tables — верификатор подтвердил наличие afterReordering).
- **Тест:** сложно юнитом (нужен Livewire-компонент); минимум — тест, что колбэк зарегистрирован,
  или вызвать reorder-логику таблицы из smoke-теста PluginTest.

### 4. Кривой route-пункт кладёт 500 всю панель (админ теряет доступ к UI)

- **Файл:** `src/Models/TopbarMenuItem.php:105-109` (`resolveUrl()`)
- **Причина:** проверяется только `Route::has()` (существование имени), затем `route($name, $params)` —
  Laravel бросает `UrlGenerationException` (RouteUrlGenerator.php:94), если у маршрута есть обязательный
  параметр, а `route_parameters` пуст. Форма позволяет это создать: Select предлагает ВСЕ именованные
  маршруты, KeyValue параметров nullable и не валидируется. Меню рендерится хуком на каждой странице
  панели → 500 везде, включая страницы ресурса, где это можно исправить. Чинить только через БД/tinker.
- **Fix:** обернуть `route()` в try/catch (`UrlGenerationException` → `return null`); пункт с null URL
  уже корректно пропускается в blade. Опционально — валидация в форме (route требует параметры → подсказка).
- **Тест:** item type=route на маршрут `/users/{user}` без параметров → `resolveUrl()` возвращает `null`, не бросает.

### 5. Опечатка в имени иконки — тоже 500 всей панели

- **Файлы:** `resources/views/partials/item-icon.blade.php:14`, `src/Filament/Resources/TopbarMenuItemResource.php:146`
- **Причина:** `icon` — свободный TextInput без валидации; `<x-filament::icon>` → blade-icons `svg()`
  (vendor/filament/support/src/helpers.php:204 → BladeUI Factory.php:150) бросает `SvgNotFound` для
  незарегистрированного имени (fallback в blade-icons не настроен). Дальше тот же сценарий, что в №4:
  кэш сброшен на save, хук на каждой странице → панель лежит, чинить через БД.
- **Fix:** в partial обернуть рендер иконки в try/catch (`\BladeUI\Icons\Exceptions\SvgNotFound` → ничего не выводить)
  — partial станет Blade с @php-блоком или вынести в метод/хелпер. Плюс (опционально) валидация поля в форме.
- **Тест:** рендер меню с item.icon='heroicon-o-does-not-exist' не бросает, label выводится без иконки.

### 6. Форма молча стирает `roles` (и любые другие ключи) из visibility

- **Файл:** `src/Filament/Resources/TopbarMenuItemResource.php:199-214` (Select 'visibility')
- **Причина:** round-trip `formatStateUsing` / `dehydrateStateUsing` знает только `auth`/`guest`.
  Было `{"roles":["admin"]}` (задокументировано в README и docblock модели) → formatState даёт null →
  dehydrate(null) = null → любое сохранение формы (даже переименование label) пишет `visibility = null`,
  пункт становится виден всем. Vendor-подтверждение: HasState::getStateToDehydrate (schemas) пишет
  результат dehydrate безусловно; EditRecord — стоковый, без mutateFormDataBeforeSave.
- **Fix:** в `dehydrateStateUsing` инжектить `?TopbarMenuItem $record` и мержить: взять текущий
  `$record?->visibility ?? []`, удалить ключи `auth`/`guest`, добавить выбранный режим; вернуть null
  только если итоговый массив пуст. formatState оставить как есть.
- **Тест:** через форму невозможно юнитом без Livewire — протестировать сами closure-функции нельзя напрямую,
  но можно вынести маппинг в статические методы модели/хелпер и покрыть их тестами.

## MINOR

### 7. `isExternalUrl()` игнорирует порт и регистр хоста

- **Файл:** `src/Models/TopbarMenuItem.php:141-149`
- **Сценарий:** app.url `http://localhost:8000`, пункт на `http://localhost:3000` → считается внутренним
  (откроется в той же вкладке при включённом `open_external_links_in_new_tab`); `https://EXAMPLE.com`
  против `https://example.com` — наоборот, считается внешним.
- **Fix:** сравнивать `strtolower(host)` + порт (учесть дефолтные 80/443 при желании; минимум — host:port, lowercase).
- **Тест:** оба сценария из описания.

### 8. FaviconResolver: относительные href резолвятся от корня хоста + результат из HTML не проверяется

- **Файл:** `src/Support/FaviconResolver.php:34, 91, 107`
- **Сценарий:** страница `https://example.com/en/docs` с `<link rel="icon" href="static/fav.png">`
  (правильно: `/en/static/fav.png`) → сохранится `https://example.com/static/fav.png`. URL из HTML
  пишется в БД без `isValidFavicon()` (проверяется только `/favicon.ico`) → битая ссылка молча
  сохраняется, img скрывается через onerror, команда рапортует успех.
- **Fix:** резолвить относительные href от директории URL страницы (учесть `../`); прогонять
  HTML-derived URL через `isValidFavicon()` перед возвратом.
- **Тест:** Http::fake со страницей на подпути и относительным href; кейс с недоступным href из HTML → null.

### 9. Hover-«мёртвая зона» 6px между пунктом и dropdown

- **Файлы:** `resources/dist/filament-topbar-menu.css:87-92`, `resources/views/menu.blade.php:32`
- **Причина:** `margin-top: .375rem` не участвует в hit-testing; медленное движение курсора вниз попадает
  в зазор → `mouseleave` на `.ftm-item` → dropdown мгновенно закрывается (leave-transition нет).
- **Fix:** заменить margin на прозрачный мостик: у `.ftm-dropdown` убрать margin-top и добавить
  `padding-top: .375rem; background: transparent` на обёртку, ЛИБО `::before` с `height:.375rem` поверх зазора,
  ЛИБО перенести отступ внутрь (`top:100%` + внутренний контейнер с фоном). Плюс можно добавить небольшой
  close-delay в Alpine (setTimeout) — опционально.

### 10. README обещает «no-op», а команда возвращает exit code 1

- **Файлы:** `src/Commands/RefreshFaviconsCommand.php:19-23`, `README.md` (раздел Favicons, «become no-ops»)
- **Сценарий:** `enable_favicons=false` + команда в deploy-скрипте с `set -e` или в scheduler
  с алертингом → ложный сигнал сбоя.
- **Fix:** возвращать `self::SUCCESS` с warning-сообщением (и/или уточнить README). Обновить тест
  `test_it_fails_when_favicons_are_disabled` → ожидать success.

---

## Дубликаты в исходном отчёте ревью

Проблема №3 (reorder/кэш) была независимо найдена тремя ревьюерами (runtime-logic, filament-api,
packaging: TopbarMenuItem.php:65, TopbarMenuItemResource.php:265, README.md:208) — это ОДНА проблема,
фиксится в одном месте + правка формулировок в README/config при необходимости (после фикса
формулировки станут правдой, менять их не нужно).

## Чек-лист реализации — ВЫПОЛНЕНО (2026-07-06)

- [x] №1 CSS clipping — убран `overflow-x: auto`/`scrollbar-width` с `.ftm-nav`, добавлен `min-width: 0`
- [x] №2 мобильная видимость — удалён мёртвый `max-width:768px` блок, поведение `lg+` задокументировано в README (раздел «Responsive behavior»)
- [x] №3 `afterReordering(fn () => app(TopbarMenu::class)->flushCache())` в table()
- [x] №4 try/catch `UrlGenerationException` в `resolveUrl()`
- [x] №5 try/catch (`\Throwable`) вокруг `generate_icon_html()` в item-icon partial
- [x] №6 `visibilityModeFromArray()` + `applyVisibilityMode()` в модели, форма мержит через `$record->visibility` (roles сохраняются)
- [x] №7 `normalizeAuthority()` (host+port, lowercase, дефолтные порты) в `isExternalUrl()`
- [x] №8 page-relative + `../` резолв в `makeAbsoluteUrl()`/`normalizePath()` + валидация HTML-derived URL через `isValidFavicon()`
- [x] №9 прозрачный `.ftm-dropdown::before`-мостик вместо голого `margin-top`
- [x] №10 `self::SUCCESS` при выключенных favicons + тест обновлён
- [x] Тесты: 40 passed / 91 assertions (было 31/70). Добавлены/обновлены тесты по пп. 3,4,5,6,7,8,10
- [x] Полный прогон: `docker run --rm -v "$PWD:/app" -w /app php84-intl php vendor/bin/phpunit` → OK
- [x] README: обещания про reorder-кэш и no-op-команду теперь соответствуют коду (код подогнан под доки)

Все 10 проблем исправлены. Этот документ можно удалить после мержа фиксов.

# Документация класса `App` (Boson Framework)

**Версия:** 2.1

Центральный диспетчер запроса. Управляет жизненным циклом: инициализация → маршрутизация → контроллер → рендеринг. Поддерживает хуки, настраиваемый CSRF, обработку исключений, middleware-пайплайн.

## Жизненный цикл запроса

```
app()->execute()
  ├─ cors()
  ├─ хуки beforeRequest
  ├─ CSRF-проверка (для POST/PUT/PATCH/DELETE)
  ├─ router()->dispatch()          ← middleware pipeline
  │    ├─ middleware (глобальные + маршрута)
  │    ├─ _before()                ← хук контроллера
  │    ├─ метод контроллера
  │    └─ _after()                 ← хук контроллера
  ├─ хуки afterResponse
  └─ theme()->display($content)
```

Необработанные исключения перехватываются `handleException()` — в debug-режиме показывает файл и строку, в production — общее сообщение.

## Конфигурация (`config.ini`)

```ini
debug        = "on"    ; on — подробные ошибки, off — общее сообщение
csrf_enabled = "on"    ; on — включена, off — отключена
```

## Хуки уровня приложения

Регистрируются в `app/Bootstrap.php`. Позволяют добавить логику без правки каждого контроллера.

```php
// Перед каждым запросом
app()->hook('beforeRequest', function() {
    if( cfg('config', 'maintenance') === 'on' && !is_auth() ) {
        abort('Сайт на обслуживании', 503);
    }
});

// После контроллера, до рендеринга (можно менять $content)
app()->hook('afterResponse', function(&$content) {
    $duration = round(microtime(true) - BOSON_START_TIME, 3);
    $content = str_replace('</body>', "<!-- {$duration}s --></body>", $content);
});
```

## Настройка CSRF

```php
// Кастомная проверка (callback должен вернуть true или строку с ошибкой)
app()->csrfChecker(function() {
    return input()->header('X-Custom-Token') === 'secret' ? true : 'Неверный токен';
});

// Полное отключение (или csrf_enabled = off в config.ini)
app()->csrfChecker(fn() => true);

// Вернуть стандартную проверку
app()->csrfChecker(null);
```

## Обработка исключений

`AppException` — прерывает выполнение с указанным сообщением и кодом 500.

Любое другое исключение (`TypeError`, `PDOException`, ...) перехватывается:
- **debug = on** — показывает класс, сообщение, файл и строку
- **debug = off** — «Внутренняя ошибка сервера»

Все исключения пишутся в `error_log()`.

## Middleware

Middleware регистрируются через роутер и выполняются через `router()->dispatch()` внутри `App::runRequest()`.

```php
// В Routes.php
router()->middleware('LoggerMiddleware');
router()->get('/admin', 'Admin@index')->middleware(['AuthMiddleware']);
```

Класс middleware должен иметь метод `handle($route, $next)`.

## Методы

| Метод | Описание |
|---|---|
| `getController()` | Текущий экземпляр контроллера |
| `hook($event, $callback)` | Зарегистрировать хук (`beforeRequest`, `afterResponse`) |
| `csrfChecker(?callable)` | Установить кастомный CSRF-проверяющий |
| `execute()` | Запустить выполнение запроса |

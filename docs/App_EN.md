# App Class Documentation (Boson Framework)

**Version:** 2.1

Central request dispatcher. Manages the lifecycle: initialization → routing → controller → rendering. Supports hooks, configurable CSRF, exception handling, middleware pipeline.

## Request Lifecycle

```
app()->execute()
  ├─ cors()
  ├─ beforeRequest hooks
  ├─ CSRF check (for POST/PUT/PATCH/DELETE)
  ├─ router()->dispatch()          ← middleware pipeline
  │    ├─ middleware (global + route)
  │    ├─ _before()                ← controller hook
  │    ├─ controller action
  │    └─ _after()                 ← controller hook
  ├─ afterResponse hooks
  └─ theme()->display($content)
```

Unhandled exceptions are caught by `handleException()` — debug mode shows file and line, production shows generic message.

## Configuration (`config.ini`)

```ini
debug        = "on"    ; on — detailed errors, off — generic message
csrf_enabled = "on"    ; on — enabled, off — disabled
```

## App-level Hooks

Register in `app/Bootstrap.php`. Add logic without touching every controller.

```php
// Before every request
app()->hook('beforeRequest', function() {
    if( cfg('config', 'maintenance') === 'on' && !is_auth() ) {
        abort('Site under maintenance', 503);
    }
});

// After controller, before rendering (can modify $content)
app()->hook('afterResponse', function(&$content) {
    $duration = round(microtime(true) - BOSON_START_TIME, 3);
    $content = str_replace('</body>', "<!-- {$duration}s --></body>", $content);
});
```

## CSRF Configuration

```php
// Custom checker (callback must return true or error string)
app()->csrfChecker(function() {
    return input()->header('X-Custom-Token') === 'secret' ? true : 'Invalid token';
});

// Full disable (or csrf_enabled = off in config.ini)
app()->csrfChecker(fn() => true);

// Restore default
app()->csrfChecker(null);
```

## Exception Handling

`AppException` — terminates execution with specified message and 500 code.

Any other exception (`TypeError`, `PDOException`, ...) is caught:
- **debug = on** — shows class, message, file, and line
- **debug = off** — "Internal server error"

All exceptions are written to `error_log()`.

## Middleware

Registered via router and executed through `router()->dispatch()` inside `App::runRequest()`.

```php
// In Routes.php
router()->middleware('LoggerMiddleware');
router()->get('/admin', 'Admin@index')->middleware(['AuthMiddleware']);
```

Middleware class must have `handle($route, $next)` method.

## Methods

| Method | Description |
|---|---|
| `getController()` | Current controller instance |
| `hook($event, $callback)` | Register hook (`beforeRequest`, `afterResponse`) |
| `csrfChecker(?callable)` | Set custom CSRF checker |
| `execute()` | Run request execution |

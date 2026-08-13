# Документация класса MicroRouter (Boson Framework)

**Namespace:** `Boson`  
**Версия:** 2.1  
**Автор:** Tishchenko Alexander  
**Файл:** `MicroRouter.php`

---

## Описание

`MicroRouter` — это лёгкий и гибкий компонент маршрутизации для микро-фреймворка Boson. Он поддерживает регистрацию маршрутов по HTTP-методам, группировку, middleware, ресурсные контроллеры, именованные маршруты и генерацию URL.

Класс реализует паттерн **Singleton**, для работы используется глобальная функция `router()`.

---

## Быстрый старт

### Регистрация простого маршрута
Маршрут связывает URI и действие контроллера в формате `Класс@метод`.

```php
router()->get('/home', 'HomeController@index');
```

### Именованный маршрут
Третий параметр задаёт имя маршрута:

```php
router()->get('/profile', 'UserController@profile', 'user.profile');
```

Если имя не указано, оно генерируется автоматически: `snake_case(контроллер) + '.' + snake_case(метод)`.

### Запуск маршрутизатора
Обработка запроса происходит автоматически через `app()->execute()`. Для ручного вызова:

```php
$result = router()->dispatch();
```

---

## Регистрация маршрутов

### HTTP-методы
Доступны методы для всех стандартных HTTP-глаголов:

```php
router()->get('/users', 'UserController@index');
router()->post('/users', 'UserController@store');
router()->put('/users/{id}', 'UserController@update');
router()->delete('/users/{id}', 'UserController@destroy');
router()->patch('/users/{id}', 'UserController@update');
router()->options('/users', 'UserController@options');
router()->head('/status', 'StatusController@head');
router()->any('/catch-all', 'FallbackController@handle'); // Любой метод
```

Все методы принимают третий параметр — имя маршрута.

### Несколько методов для одного пути
Метод `match` позволяет зарегистрировать один обработчик для нескольких методов:

```php
router()->match(['GET', 'POST'], '/contact', 'ContactController@handle');
```

---

## Параметры маршрута

### Обязательные параметры
Обозначаются фигурными скобками. Значение передаётся в метод контроллера как аргумент.

```php
// URI: /user/123
// Вызов: UserController@show('123')
router()->get('/user/{id}', 'UserController@show');
```
*По умолчанию параметр соответствует шаблону:* `[0-9a-zA-Z\-\_]+`

### Необязательные параметры
Обозначаются знаком вопроса внутри скобок.

```php
// URI: /article или /article/45
router()->get('/article/{slug?}', 'ArticleController@show');
```

### Пользовательские паттерны (Regex)
Можно задать своё регулярное выражение для параметра через метод `pattern()`.

```php
// Параметр {id} теперь принимает только цифры
router()->pattern('id', '\d+');

router()->get('/post/{id}', 'PostController@show');
```

### Удобные хелперы паттернов

```php
router()->whereNumber('id');          // только цифры [0-9]+
router()->whereAlpha('slug');         // только буквы [a-zA-Z]+
router()->whereAlphaNumeric('slug');  // буквы и цифры [a-zA-Z0-9]+
```

---

## Группы маршрутов

Группы позволяют задавать общий префикс, middleware и префикс имен для вложенных маршрутов.

```php
router()->group([
    'prefix' => '/admin',
    'middleware' => ['AuthMiddleware', 'AdminCheckMiddleware'],
    'name' => 'admin'
], function ($router) {
    
    // Путь: /admin/dashboard
    // Имя: admin.dashboard
    $router->get('/dashboard', 'Admin\DashboardController@index', 'dashboard');
    
    // Путь: /admin/users
    // Имя: admin.users
    $router->get('/users', 'Admin\UserController@index', 'users');
});
```

**Атрибуты группы:**
*   `prefix` (string): Добавляется к началу пути всех маршрутов в группе.
*   `middleware` (array|string): Middleware, применяемые ко всем маршрутам группы.
*   `name` (string): Префикс для имён маршрутов. Точка в конце добавляется автоматически, если её нет.

---

## Ресурсные контроллеры (REST)

Метод `resource` автоматически регистрирует набор CRUD маршрутов для контроллера. Контроллер должен реализовывать интерфейс `\Boson\Interfaces\Resource`.

```php
router()->resource('/photos', 'PhotoController');
```

**Созданные маршруты:**

| Метод | URI | Действие | Имя маршрута |
| :--- | :--- | :--- | :--- |
| GET | `/photos` | index | `photo_controller.index` |
| GET | `/photos/create` | create | `photo_controller.create` |
| POST | `/photos` | store | `photo_controller.store` |
| GET | `/photos/{id}` | show | `photo_controller.show` |
| GET | `/photos/{id}/edit` | edit | `photo_controller.edit` |
| PUT | `/photos/{id}` | update | `photo_controller.update` |
| PATCH | `/photos/{id}` | update | `photo_controller.update.patch` |
| DELETE | `/photos/{id}` | destroy | `photo_controller.destroy` |

---

## Middleware

### Глобальные Middleware
Применяются ко **всем** зарегистрированным маршрутам.

```php
router()->middleware('GlobalCsrfMiddleware');
router()->middleware(['StartSession', 'ShareErrorsFromSession']);
```

### Middleware маршрута
Можно указать непосредственно при регистрации маршрута:

```php
router()->get('/profile', [
    'controller' => 'UserController',
    'method'     => 'show',
    'middleware' => ['AuthMiddleware']
]);
```

### Реализация Middleware
Middleware должен быть классом с методом `handle`. Сигнатура: `handle($route, $next)`, где `$route` — массив данных маршрута, а `$next` — callable для передачи управления дальше.

```php
class AuthMiddleware {
    public function handle($route, $next) {
        if (!is_auth()) {
            redirect('/login');
        }
        return $next();
    }
}
```

---

## Fallback-маршрут (404)

Метод `fallback` регистрирует обработчик для случаев, когда ни один маршрут не совпал:

```php
router()->fallback('Errors@notFound');
// или с middleware:
router()->fallback(['controller' => 'Errors', 'method' => 'notFound']);
```

Fallback-маршрут работает только при вызове `dispatch()`. В стандартном потоке `app()->execute()` 404 обрабатывается через `abort()` в `App::__construct()`.

---

## Именованные маршруты и генерация URL

### Присвоение имени
Имя задаётся третьим параметром при регистрации или в массиве данных:

```php
router()->get('/login', 'AuthController@login', 'user.login');
```

Если имя не указано, генерируется автоматически из `snake_case` контроллера и метода.

### Получение пути по имени
Метод `getPathByName` генерирует URL, подставляя параметры.

```php
// Маршрут: /user/{id} (имя: users.show)
$url = router()->getPathByName('users.show', ['id' => 5]);
// Результат: /user/5
```

### Редирект по имени
```php
router()->redirect('users.show', ['id' => 10]);
```

---

## Справочник методов (API Reference)

### Основные методы

| Метод | Описание | Параметры | Возвращает |
| :--- | :--- | :--- | :--- |
| `dispatch()` | Найти маршрут, выполнить middleware и контроллер. | - | `mixed` |
| `getRequestUri()` | Получить текущий нормализованный URI. | - | `string` |
| `getRoute()` | Получить массив данных текущего найденного маршрута. | - | `?array` |
| `getParams()` | Получить параметры текущего маршрута. | - | `array` |
| `routes()` | Получить список всех зарегистрированных маршрутов. | - | `array` |

### Регистрация

| Метод | Описание |
| :--- | :--- |
| `get($path, $data, $name?)` | GET маршрут. |
| `post($path, $data, $name?)` | POST маршрут. |
| `put($path, $data, $name?)` | PUT маршрут. |
| `delete($path, $data, $name?)` | DELETE маршрут. |
| `patch($path, $data, $name?)` | PATCH маршрут. |
| `any($path, $data, $name?)` | Любой HTTP-метод. |
| `match($methods, $path, $data, $name?)` | Массив методов. |
| `resource($path, $controller)` | REST ресурс. |
| `group($attributes, $callback)` | Группа маршрутов. |
| `middleware($middleware)` | Глобальный middleware. |
| `fallback($data)` | Обработчик для ненайденных маршрутов. |

### Параметры

| Метод | Описание |
| :--- | :--- |
| `pattern($key, $pattern)` | Задать regex-паттерн для параметра. |
| `whereNumber($name)` | Параметр только из цифр `[0-9]+`. |
| `whereAlpha($name)` | Параметр только из букв `[a-zA-Z]+`. |
| `whereAlphaNumeric($name)` | Параметр из букв и цифр `[a-zA-Z0-9]+`. |

### Утилиты

| Метод | Описание |
| :--- | :--- |
| `getPathByName($name, $values)` | Генерирует URL по имени маршрута. |
| `redirect($name, $values)` | HTTP редирект по имени маршрута. |
| `isRouteNameExists($name)` | Проверяет существование имени. |
| `getAllRoutes()` | Все зарегистрированные маршруты (аналог `routes()`). |

---

## Исключения

Класс может выбрасывать исключение `Boson\MicroRouterException` в следующих случаях:
*   Некорректный тип запроса.
*   Контроллер или метод не найдены.
*   Маршрут не найден при вызове `dispatch()` (если не задан fallback).
*   Ошибка при генерации URL (отсутствуют параметры).
*   Контроллер ресурса не реализует нужный интерфейс.

```php
try {
    router()->dispatch();
} catch (\Boson\MicroRouterException $e) {
    error_log($e->getMessage());
}
```

---

## Конвенции

1.  **Контроллеры:** Ожидаются в пространстве имен `\App\Controllers\`. Файл контроллера должен лежать в `app/controllers/`.
2.  **Формат действия:** Строка `'Controller@method'` или массив `['controller' => '...', 'method' => '...']`.
3.  **URI:** Всегда начинаются с `/`. Внутренняя нормализация убирает дублирующиеся слеши и лишние расширения (`.html`, `.php` и т.д.).
4.  **Имена маршрутов:** Автоматически генерируются в формате `controller.method` (snake_case), если не заданы явно. Групповой префикс добавляется с точкой.

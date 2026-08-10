# BOSON PHP8 MVC micro-framework

Легковесный PHP micro-framework для быстрой разработки веб-приложений. 
Создан для тех случаев, когда нужно написать простое веб-приложение легко и быстро, 
без перегруженных конфигураций и сложных абстракций.

**Автор:** Тищенко Александр ([alexander_lg@mail.ru](mailto:alexander_lg@mail.ru) / info@alex-tisch.ru)

---

## Возможности

- **Минимум настроек** — всё работает «из коробки», достаточно настроить подключение к БД
- **MVC-структура** — чёткое разделение логики, представления и данных
- **Eloquent ORM** — полноценная работа с базой данных через Laravel Eloquent 9.x
- **Smarty / PHTML** — два режима шаблонизации: мощный Smarty 5.8 или нативный PHTML, горячая замена
- **Мультиязычность** — встроенная поддержка интернационализации (i18n)
- **Гибкий роутинг** — middleware-пайплайн, именованные маршруты, группы, ресурсные контроллеры, fallback
- **Безопасный ввод** — XSS-фильтр, типизированный доступ (`int`, `bool`, `float`, `date`, `array`)
- **Валидация** — pipe-синтаксис правил, кастомные валидаторы, `date`, `confirmed`, `json`, `alpha`
- **Встроенная аутентификация** — собственный класс `Auth` + JWT-токены (firebase/php-jwt)
- **Кеширование в БД** — TableCache для быстрого кеширования без внешних зависимостей
- **MCP-сервер** — встроенная поддержка Model Context Protocol для AI-интеграций
- **WebSocket** — поддержка реального времени (cboden/ratchet, textalk/websocket)
- **Хуки приложения** — `beforeRequest` / `afterResponse` без правки контроллеров
- **Обработка исключений** — перехват всех `\Throwable`, debug/production режимы

---

## Технический стек

| Компонент          | Технология                                                    |
|--------------------|---------------------------------------------------------------|
| Язык               | PHP ^8.1 <8.3                                                 |
| ORM                | Laravel Eloquent 9.x                                          |
| Шаблонизатор       | Smarty 5.8 (основной) или нативный PHTML                      |
| Роутинг            | Собственный `Boson\MicroRouter` (v2.1)                        |
| HTTP-сервер        | Apache (`.htaccess`) или Nginx (`.nginx`)                      |
| База данных        | MySQL                                                         |
| Аутентификация     | `App\Library\Auth` + firebase/php-jwt 7.0                     |
| WebSocket          | cboden/ratchet 0.4.4, textalk/websocket 1.5                   |
| AI-интеграции      | deepseek-php/deepseek-php-client 2.0                          |
| Дополнительно      | guzzlehttp/guzzle, phpmailer/phpmailer, intervention/image, barryvdh/laravel-dompdf, maatwebsite/excel, nesbot/carbon |

---

## Быстрый старт

### Требования

- PHP ^8.1 <8.3
- MySQL
- Composer
- Apache (mod_rewrite) или Nginx + PHP-FPM

### Установка

```bash
# 1. Клонируйте репозиторий
git clone <repo-url> .
cd public_html

# 2. Установите зависимости
cd app && composer install

# 3. Настройте подключение к БД в app/configs/database.ini
#    и остальные параметры в app/configs/config.ini

# 4. Локальный запуск (встроенный сервер PHP)
php -S localhost:8000
```

Готово! Приложение доступно по адресу [http://localhost:8000](http://localhost:8000).

---

## Структура проекта

```
public_html/
├── index.php                  # Точка входа: инициализация констант, загрузка ядра
├── .htaccess                  # Apache: редирект на index.php, запрет доступа к чувствительным файлам
├── .nginx                     # Nginx: аналог конфигурации
├── app/
│   ├── Bootstrap.php          # Пользовательский bootstrap: auth(), csrf, хуки, cors
│   ├── Routes.php             # Регистрация всех маршрутов приложения
│   ├── composer.json          # Зависимости (PHP ^8.1 <8.3)
│   ├── Functions.php          # (опционально) Пользовательские функции
│   ├── configs/
│   │   ├── config.ini         # Основная конфигурация (theme, debug, csrf, headers)
│   │   ├── database.ini       # Параметры подключения к БД (секция [default])
│   │   ├── mailer.ini         # Настройки почты
│   │   └── deepseek.ini       # API-ключ DeepSeek
│   ├── controllers/           # Контроллеры (Index, Install, Users, Photo, Passwords, Mcp)
│   ├── models/                # Eloquent-модели (User, Profile, Password и др.)
│   ├── library/               # Библиотеки приложения (Auth, DeepSeekClient, Ping, Reconstructor)
│   └── lang/                  # Файлы перевода (ru.php, en.php)
├── boson/                     # Ядро фреймворка
│   ├── Bootstrap.php          # Загрузка всех классов ядра, автолоадера, запуск приложения
│   ├── Constants.php          # Константы (APP_DIR, TEMP_DIR, CONTENT_DIR и др.)
│   ├── Functions.php          # ~1600+ строк вспомогательных функций (cfg(), encrypt(), redirect()...)
│   ├── Helpers.php            # Хелперы-алиасы: input(), session(), router(), app(), i18n()...
│   ├── Classes/               # Классы ядра (App, MicroRouter, Input, Session, Theme, Validator...)
│   ├── Abstracts/             # Абстрактные классы (EloquentModel, Registry)
│   ├── Interfaces/            # Интерфейсы (Resource)
│   └── Traits/                # Трейты (SingletonTrait, ClassName)
├── themes/
│   ├── default/               # Тема по умолчанию (css, js, fonts, views — PHTML)
│   └── smarty/                # Smarty-тема (css, js, fonts, views — .tpl)
├── content/                   # Публичные файлы (доступны через /content/), gitignored
└── temp/                      # Временные файлы (smarty-кэш и пр.), gitignored
```

---

## Конфигурация (config.ini)

```ini
theme  = "smarty"                              ; Папка темы в themes/
layout = "layout"                              ; Файл макета
cover  = "smarty"                              ; Движок: smarty или native (PHTML)

debug  = "on"                                  ; Режим отладки (on — подробные ошибки)
csrf_enabled = "on"                            ; CSRF-защита (off чтобы отключить)

x_frame_options = "DENY"                       ; Защитный заголовок (0 — отключить)
x_content_type_options = "nosniff"
referrer_policy = "strict-origin-when-cross-origin"
```

---

## Жизненный цикл запроса

1. **`index.php`** определяет константы (`ROOT`, `BASE_URL`, `PROTOCOL`...) и временную зону `Europe/Moscow`.
2. Загружается **`boson/Bootstrap.php`** → `Constants.php` → `Functions.php` → классы ядра → Composer autoload.
3. Создаются временные/контентные папки, если отсутствуют.
4. Подключаются модели из `app/models/`, библиотеки из `app/library/`.
5. Загружаются `app/Functions.php` (если есть) и **`app/Routes.php`**.
6. Загружается **`app/Bootstrap.php`** (пользовательский bootstrap: auth, csrf, хуки).
7. Вызывается **`app()->execute()`**:
   - `App::__construct()`: инициализирует orm, i18n, input, theme, session, cookies, ищет роут и создаёт контроллер.
   - `App::execute()`: cors → хуки `beforeRequest` → CSRF → `_before()` → действие контроллера (через middleware-пайплайн) → `_after()` → хуки `afterResponse` → `theme()->display()`.
   - Необработанные исключения перехватываются `handleException()` — в debug режиме показывается файл и строка, в production — общее сообщение.

---

## Роутинг

Роутинг определяется в `app/Routes.php`. Версия MicroRouter 2.1: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD, ANY, ресурсные контроллеры, группы, middleware, fallback.

### Регистрация маршрутов

```php
// Параметры с авто-паттернами
router()->whereNumber('id');            // только цифры [0-9]+
router()->whereAlphaNumeric('query');   // буквы и цифры

// Базовые маршруты
router()->get('/', 'Index@index');
router()->get('/users/{id}', 'Users@show');

// POST, PUT, DELETE
router()->post('/users', 'Users@create', 'users.create');
router()->put('/users/{id}', 'Users@update', 'users.update');
router()->delete('/users/{id}', 'Users@remove', 'users.remove');

// Цепочка с явными именами
router()->any('/login', 'Index@login', 'index.login')
        ->any('/logout', 'Index@logout', 'index.logout');

// Ресурсные контроллеры (index, create, store, show, edit, update, destroy)
router()->resource('/photo', 'Photo');

// Группы — префикс пути, неймспейс имён, middleware
router()->group(['prefix' => 'admin', 'name' => 'admin', 'middleware' => ['AuthMiddleware']], function($router) {
    $router->get('/dashboard', 'Dashboard@index', 'dashboard');
    // Итоговый путь: /admin/dashboard, имя: admin.dashboard
});

// Fallback-маршрут (404) — при использовании router()->dispatch()
router()->fallback('Errors@notFound');

// Глобальный middleware
router()->middleware('GlobalLogger');
```

### Middleware

Middleware-класс должен иметь метод `handle($route, $next)`. Пайплайн работает через `router()->dispatch()` (вызывается из `App::execute()`).

```php
class AuthMiddleware {
    public function handle($route, $next) {
        if (!is_auth()) {
            redirect('/login');
        }
        return $next(); // передать управление дальше по цепочке
    }
}
```

---

## Контроллеры

Контроллеры расположены в `app/controllers/`, namespace `App\Controllers`.

```php
<?php

namespace App\Controllers;

class Index
{
    // Выполняется ПЕРЕД вызываемым методом
    public function _before() { }

    // Выполняется ПОСЛЕ вызываемого метода
    public function _after() { }

    // Рендер Smarty-шаблона
    public function index()
    {
        theme()->assign('is_auth', is_auth());
        return view('index/index');            // themes/{theme}/views/index/index.tpl
    }

    // JSON-ответ
    public function data($user_id = null)
    {
        return json_response([
            'status' => 'success',
            'user'   => user($user_id),
        ]);
    }
}
```

### Хуки уровня приложения

Вместо дублирования кода в каждом `_before()`, можно зарегистрировать глобальные хуки в `app/Bootstrap.php`:

```php
app()->hook('beforeRequest', function() {
    // Проверка тех.работ перед каждым запросом
    if( cfg('config', 'maintenance') === 'on' && !is_auth() ) {
        abort('Сайт на обслуживании', 503);
    }
});

app()->hook('afterResponse', function(&$content) {
    // Добавить время генерации в футер
    $duration = round(microtime(true) - BOSON_START_TIME, 3);
    $content = str_replace('</body>', "<!-- {$duration}s --></body>", $content);
});
```

### Настройка CSRF

```php
// Отключить (или через config.ini: csrf_enabled = off)
app()->csrfChecker(fn() => true);

// Кастомная проверка
app()->csrfChecker(function() {
    $token = input()->header('X-Custom-Token');
    return $token === 'my-secret' ? true : 'Неверный токен';
});
```

---

## Работа с входными данными (Input v2.1)

Класс `Input` — единая точка доступа ко всем параметрам запроса. GET, POST, JSON-тело, заголовки, файлы, cookies. Автоматическая XSS-очистка. Типизированный доступ.

### Базовое получение

```php
$email = input('email');              // XSS-очищенное значение
$all   = input()->all();              // Все параметры массивом

input()->filled('email');             // Ключ есть и не пуст
input()->missing('token');            // Ключа нет или пуст
```

### Типизированный доступ (без XSS, быстрее)

```php
$id       = input()->int('id', 0);          // (int)
$price    = input()->float('price', 0.0);   // (float)
$active   = input()->bool('active');        // '1'/'true'/'yes'/'on' → true
$name     = input()->string('name');        // (string) с XSS
$tags     = input()->array('tags', []);     // (array)
$birthday = input()->date('birthday');      // DateTime или null
```

### Раздельный доступ к GET и POST

```php
$page = input()->query('page', 1);    // только $_GET
$body = input()->post('email');       // только $_POST
```

### Проверки запроса

```php
input()->isJson();         // Content-Type: application/json?
input()->expectsJson();    // AJAX или Accept: /json?
input()->isPost();         // HTTP метод POST?
input()->method();         // 'GET', 'POST', 'PUT'...
input()->bearerToken();    // Bearer токен из Authorization
```

---

## Валидация (Validator v2.1)

Строковый pipe-синтаксис, кастомные правила, i18n-сообщения.

```php
$validator = validator($data, [
    'email'    => 'required|email|maxlen:255',
    'password' => 'required|minlen:6|confirmed',
    'age'      => 'required|int|min:18|max:99',
    'birthday' => 'date:Y-m-d',
    'gender'   => 'integer|in:0,1,2',
    'tags'     => 'json',
    'slug'     => 'alpha|minlen:3',
]);

if( $validator->fails() ) {
    return json_response(['errors' => $validator->errors()], 422);
}

$clean = $validator->validated();  // только поля с правилами

// Кастомные правила
$validator->addRule('even', function($value, $params, $allValues) {
    return $value % 2 === 0;
});

// Пользовательские сообщения
$validator->setMessages([
    'email.required' => 'Укажите email',
    'age.min'        => 'Возраст не менее 18 лет',
]);
```

**Поддерживаемые правила:** `required`, `nullable`, `trim`, `int`/`integer`, `float`, `bool`/`boolean`, `numeric`, `email`, `url`, `json`, `alpha`, `alphanum`, `date`, `date:Y-m-d`, `min:N`, `max:N`, `minlen:N`, `maxlen:N`, `in:a,b,c`, `not_in:x,y,z`, `same:field`, `confirmed`, `regexp:/.../`, `validator:name`.

---

## Модели и работа с базой данных

Модели расположены в `app/models/`, наследуют `Boson\Abstracts\EloquentModel` (расширяет `Illuminate\Database\Eloquent\Model`). 
Конфигурация БД — в `app/configs/database.ini`.

```php
<?php

namespace App\Models;

class User extends \Boson\Abstracts\EloquentModel
{
    protected $table = 'users';

    public function profile()
    {
        return $this->hasOne(\App\Models\Profile::class);
    }

    public function posts()
    {
        return $this->hasMany(\App\Models\Post::class);
    }
}
```

### Глобальные хелперы для работы с БД

| Функция                  | Назначение                                          |
|--------------------------|-----------------------------------------------------|
| `orm()`                  | Экземпляр `Boson\Eloquent`                          |
| `db($connection)`        | `Illuminate\Database\MySqlConnection`               |
| `table($table, $conn?)`  | `Illuminate\Database\Query\Builder`                 |
| `schema($connection?)`   | `Illuminate\Database\Schema\MySqlBuilder`           |
| `cfg('database')`        | Конфигурация БД как `BosonObject`                   |

---

## Шаблоны и темы

- **Основная тема** задаётся в `config.ini` (ключ `theme`): `theme = "smarty"`
- **Движок** задаётся ключом `cover`: `smarty` или `native` (PHTML). Горячая замена — достаточно изменить значение.
- Шаблоны расположены в `themes/{theme}/views/`
- Функция `view('path/to/view')` рендерит шаблон текущим движком
- Функция `theme()` возвращает экземпляр `Boson\Theme`

### Переменные шаблона

```php
// В контроллере:
theme()->assign('title', 'Моя страница');
theme()->assign('users', $users);
```

Глобальные переменные (доступны всегда): `{$base_url}`, `{$js_url}`, `{$css_url}`, `{$images_url}`, `{$content_url}`.

### Динамический CSS/JS

```php
// В контроллере — добавить стиль или скрипт на лету:
theme()->useThemeCss('extra.css');
theme()->useThemeJs('widget.js', $head = false);  // перед </body>
theme()->useExternalJs('https://cdn.example.com/lib.js');
```

В шаблоне доступны переменные: `{$boson_css}`, `{$boson_js_head}`, `{$boson_js_body}` — массивы URL для самостоятельной вставки. Если шаблон их не использует — работает авто-инжекция через regex (совместимость).

### Smarty

В шаблонах доступны плагины:
- `{i18n str="ключ"}` — перевод строки
- `{num2word number=n words=['год','года','лет']}` — склонение числительных

Регистрация своих плагинов:
```php
theme()->addPlugin('function', 'myplugin', 'smarty_function_myplugin');
```

### PHTML

Нативный PHP-шаблонизатор. Используйте `.phtml`-расширение. Переменные доступны как `$var`, глобальные функции — напрямую.

### Защитные заголовки

Настраиваются в `config.ini`: `x_frame_options`, `x_content_type_options`, `referrer_policy`. Значение `0` отключает заголовок.

---

## Интернационализация (i18n)

Файлы перевода: `app/lang/{locale}.php`, возвращают ассоциативный массив `['key' => 'value']`.

```php
// app/lang/ru.php
return [
    'welcome'     => 'Добро пожаловать',
    'hello_user'  => 'Привет, %s!',
];

// Использование
echo i18n('welcome');                          // "Добро пожаловать"
echo i18n()->get('hello_user', ['Александр']); // "Привет, Александр!"
```

Установка текущей локали:
```php
i18n()->setLocale('ru');
```

---

## Аутентификация

Реализована через `App\Library\Auth` и JWT-токены.

```php
// Проверка авторизации
if (is_auth()) {
    // Пользователь авторизован
    $user = auth()->user();
}

// Ручная авторизация
auth()->login($user);
auth()->logout();
```

---

## Ключевые глобальные хелперы и функции

| Функция                              | Назначение                                                    |
|--------------------------------------|---------------------------------------------------------------|
| `cfg($file, $key?)`                  | Чтение `.ini` конфигурации (кэшируется)                       |
| `input($key?, $default?)`            | XSS-очищенное значение из GET/POST/body                       |
| `input()->int($k)` / `bool($k)` / `float($k)` | Типизированный доступ без XSS                         |
| `input()->query($k?)` / `post($k?)`  | Значение строго из GET или POST                               |
| `input()->isJson()` / `expectsJson()` | Проверка типа запроса                                        |
| `session()`                          | Работа с сессиями                                             |
| `cookies()`                          | Работа с cookies                                              |
| `router()`                           | Маршрутизатор (MicroRouter v2.1)                              |
| `app()`                              | Приложение (хуки, CSRF, жизненный цикл)                       |
| `i18n($key?)`                        | Перевод строки                                                |
| `view($name, $data?)`                | Рендер шаблона текущим движком                                |
| `json_response($data, $code?)`       | JSON-ответ с HTTP-статусом                                    |
| `redirect($url, $status?)`           | HTTP-редирект                                                 |
| `abort($message, $code?)`            | Завершение с HTTP-статусом ошибки                             |
| `auth()`                             | Экземпляр `App\Library\Auth`                                  |
| `is_auth()`                          | Проверка авторизации пользователя                             |
| `encrypt($str, $key?)` / `decrypt()` | Обратимое шифрование (RC4)                                    |
| `password_crypt($pass, $salt?)`      | Хеширование пароля                                            |
| `uuid()`                             | Генерация UUID v4                                             |
| `orm()`                              | Eloquent ORM                                                  |
| `cache($key, $val?, $ttl?)`          | Кеширование через таблицу `cache` в БД                        |
| `make_url($url)`                     | Построение абсолютного URL                                    |
| `theme()`                            | Экземпляр `Boson\Theme`                                       |

---

## Кеширование

Фреймворк использует кеширование на основе таблицы `cache` в БД (`Boson\TableCache`).

```php
// Сохранить в кеш на 3600 секунд
cache('my_key', $data, 3600);

// Получить из кеша
$data = cache('my_key');

// Удалить из кеша
cache('my_key', null);
```

---

## MCP-сервер

Встроенная поддержка Model Context Protocol (MCP) для AI-интеграций через контроллер `Mcp`. Позволяет AI-агентам взаимодействовать с приложением через стандартизированный протокол.

| Маршрут           | Метод | Назначение                     |
|-------------------|-------|--------------------------------|
| `/mcp`            | POST  | Инициализация соединения       |
| `/mcp/initialize` | GET   | Capabilities и serverInfo      |
| `/mcp/tools/list` | GET   | Список доступных инструментов  |
| `/mcp/tools/call` | POST  | Вызов инструмента              |

---

## Безопасность

- Конфиденциальные данные (API-ключи, пароли БД) хранятся в `.ini` файлах внутри `app/configs/`.
- **Не коммитьте реальные ключи в репозиторий.**
- Директории `content/` и `temp/` исключены из git (`.gitignore`), создаются автоматически при отсутствии.
- Доступ к чувствительным файлам (`*.ini`, `composer.json`, `.git/`) блокируется через `.htaccess`.
- CSRF-защита включена по умолчанию для всех мутирующих методов. Отключается в `config.ini` (`csrf_enabled = off`) или через `app()->csrfChecker()`.
- XSS-очистка всех строковых параметров — автоматическая. Типизированные методы (`int`, `float`, `bool`) работают без XSS.
- Защитные HTTP-заголовки: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` — настраиваются в `config.ini`.

---

## Сборка и запуск

```bash
# Установка зависимостей
cd app && composer install

# Локальный сервер (PHP built-in)
php -S localhost:8000

# Продакшен: Apache с mod_rewrite или Nginx + PHP-FPM
```

---

## Примечания

- **Временная зона:** `Europe/Moscow` (задаётся в `index.php`)
- **Стиль кода:** классы — PascalCase, функции — snake_case
- **Пространства имён:** `Boson\*` для ядра, `App\*` для приложения
- **Комментарии в коде:** преимущественно на русском языке
- **Версии компонентов:** MicroRouter 2.1, Input 2.1, Validator 2.1, Theme 2.1, App 2.1
- Проект находится в активной разработке и постоянно дорабатывается

---

## Автор

**Тищенко Александр**

- Email: [alexander_lg@mail.ru](mailto:alexander_lg@mail.ru)
- Доп. email: [info@alex-tisch.ru](mailto:info@alex-tisch.ru)

---

## Лицензия

MIT License.

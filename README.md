# BOSON PHP8 MVC micro-framework

A lightweight PHP micro-framework for rapid web application development. Designed for cases where you need to build a simple web app quickly without heavy configurations or complex abstractions.

**Author:** Tishchenko Alexander ([alexander_lg@mail.ru](mailto:alexander_lg@mail.ru) / info@alex-tisch.ru)

[Русская версия](README_RU.md)

---

## Features

- **Minimal configuration** — works out of the box, just set up the database connection
- **MVC structure** — clear separation of logic, presentation, and data
- **Eloquent ORM** — full-featured database access via Laravel Eloquent 9.x
- **Smarty / PHTML** — two template engines: powerful Smarty 5.8 or native PHTML, hot-swappable
- **i18n** — built-in internationalization support
- **Flexible routing** — middleware pipeline, named routes, groups, resource controllers, fallback
- **Safe input** — XSS filter, typed access (`int`, `bool`, `float`, `date`, `array`)
- **Validation** — pipe-syntax rules, custom validators, 25+ built-in rules
- **Authentication** — custom `Auth` class + JWT tokens (firebase/php-jwt)
- **DB-backed cache** — TableCache for fast caching without external dependencies
- **MCP server** — built-in Model Context Protocol support for AI integrations
- **WebSocket** — real-time support (cboden/ratchet, textalk/websocket)
- **App-level hooks** — `beforeRequest` / `afterResponse` without touching controllers
- **Exception handling** — catches all `\Throwable`, debug/production modes

---

## Tech Stack

| Component     | Technology                                                |
|---------------|-----------------------------------------------------------|
| Language      | PHP ^8.1 <8.3                                             |
| ORM           | Laravel Eloquent 9.x                                      |
| Templating    | Smarty 5.8 (primary) or native PHTML                      |
| Routing       | Custom `Boson\MicroRouter` (v2.1)                         |
| HTTP Server   | Apache (`.htaccess`) or Nginx (`.nginx`)                  |
| Database      | MySQL                                                     |
| Auth          | `App\Library\Auth` + firebase/php-jwt 7.0                 |
| WebSocket     | cboden/ratchet 0.4.4, textalk/websocket 1.5               |
| AI            | deepseek-php/deepseek-php-client 2.0                      |
| Other         | guzzlehttp/guzzle, phpmailer/phpmailer, intervention/image, barryvdh/laravel-dompdf, maatwebsite/excel, nesbot/carbon |

---

## Quick Start

### Requirements

- PHP ^8.1 <8.3
- MySQL
- Composer
- Apache (mod_rewrite) or Nginx + PHP-FPM

### Installation

```bash
# 1. Clone the repository
git clone <repo-url> .
cd public_html

# 2. Install dependencies
cd app && composer install

# 3. Configure database in app/configs/database.ini
#    and other settings in app/configs/config.ini

# 4. Local dev server
php -S localhost:8000
```

Open [http://localhost:8000](http://localhost:8000).

---

## Project Structure

```
public_html/
├── index.php                  # Entry point: constants, bootstrap
├── .htaccess                  # Apache: rewrite to index.php, block sensitive files
├── .nginx                     # Nginx: equivalent config
├── app/
│   ├── Bootstrap.php          # App bootstrap: auth(), csrf, hooks, cors
│   ├── Routes.php             # Route definitions
│   ├── composer.json          # Dependencies (PHP ^8.1 <8.3)
│   ├── Functions.php          # (optional) Custom functions
│   ├── configs/
│   │   ├── config.ini         # Main config (theme, debug, csrf, headers)
│   │   ├── database.ini       # DB connection (section [default])
│   │   ├── mailer.ini         # Mail settings
│   │   └── deepseek.ini       # DeepSeek API key
│   ├── controllers/           # Controllers (Index, Install, Users, Photo, Passwords, Mcp)
│   ├── models/                # Eloquent models (User, Profile, Password, etc.)
│   ├── library/               # App libraries (Auth, DeepSeekClient, Ping, Reconstructor)
│   └── lang/                  # Translation files (ru.php, en.php)
├── boson/                     # Framework core
│   ├── Bootstrap.php          # Core loader: all classes, autoloader, app launch
│   ├── Constants.php          # Constants (APP_DIR, TEMP_DIR, CONTENT_DIR, etc.)
│   ├── Functions.php          # ~1600+ lines of helper functions
│   ├── Helpers.php            # Shortcut aliases: input(), session(), router(), app(), i18n()
│   ├── Classes/               # Core classes (App, MicroRouter, Input, Theme, Validator...)
│   ├── Abstracts/             # Abstract classes (EloquentModel, Registry)
│   ├── Interfaces/            # Interfaces (Resource)
│   └── Traits/                # Traits (SingletonTrait, ClassName)
├── themes/
│   ├── default/               # PHTML theme (css, js, fonts, views)
│   └── smarty/                # Smarty theme (css, js, fonts, views)
├── content/                   # Public files (accessible via /content/), gitignored
└── temp/                      # Temp files (Smarty cache, etc.), gitignored
```

---

## Configuration (config.ini)

```ini
theme  = "smarty"                              # Theme folder in themes/
layout = "layout"                              # Layout file name
cover  = "smarty"                              # Engine: smarty or native (PHTML)

debug  = "on"                                  # Debug mode (on — detailed errors)
csrf_enabled = "on"                            # CSRF protection (off to disable)

x_frame_options = "DENY"                       # Security header (0 to disable)
x_content_type_options = "nosniff"
referrer_policy = "strict-origin-when-cross-origin"
```

---

## Request Lifecycle

1. `index.php` defines constants (`ROOT`, `BASE_URL`, `PROTOCOL`...) and timezone `Europe/Moscow`.
2. `boson/Bootstrap.php` loads → `Constants.php` → `Functions.php` → core classes → Composer autoload.
3. Temp/content directories created if missing.
4. Models and libraries loaded from `app/models/` and `app/library/`.
5. `app/Routes.php` loaded.
6. `app/Bootstrap.php` loaded (auth, csrf, hooks).
7. `app()->execute()`:
   - `App::__construct()`: initializes orm, i18n, input, theme, session, cookies, finds route, creates controller.
   - `App::execute()`: cors → `beforeRequest` hooks → CSRF → `_before()` → controller action (via middleware pipeline) → `_after()` → `afterResponse` hooks → `theme()->display()`.
   - Unhandled exceptions caught by `handleException()` — debug mode shows file/line, production shows generic message.

---

## Routing

Defined in `app/Routes.php`. MicroRouter v2.1: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD, ANY, resource controllers, groups, middleware, fallback.

```php
// Parameter patterns
router()->whereNumber('id');            // digits only [0-9]+
router()->whereAlphaNumeric('query');   // letters and digits

// Basic routes
router()->get('/', 'Index@index');
router()->get('/users/{id}', 'Users@show');

// Named routes (3rd parameter)
router()->post('/users', 'Users@create', 'users.create');
router()->put('/users/{id}', 'Users@update', 'users.update');
router()->delete('/users/{id}', 'Users@remove', 'users.remove');

// Chaining
router()->any('/login', 'Index@login', 'index.login')
        ->any('/logout', 'Index@logout', 'index.logout');

// Resource controllers
router()->resource('/photo', 'Photo');

// Groups — path prefix, name namespace, middleware
router()->group(['prefix' => 'admin', 'name' => 'admin', 'middleware' => ['AuthMiddleware']], function($router) {
    $router->get('/dashboard', 'Dashboard@index', 'dashboard');
    // Result: /admin/dashboard, name: admin.dashboard
});

// Fallback (404)
router()->fallback('Errors@notFound');

// Global middleware
router()->middleware('LoggerMiddleware');
```

### Middleware

```php
class AuthMiddleware {
    public function handle($route, $next) {
        if (!is_auth()) redirect('/login');
        return $next(); // pass control down the chain
    }
}
```

---

## Controllers

Located in `app/controllers/`, namespace `App\Controllers`.

```php
<?php
namespace App\Controllers;

class Index
{
    public function _before() { }  // runs BEFORE the action
    public function _after() { }   // runs AFTER the action

    public function index()
    {
        theme()->assign('is_auth', is_auth());
        return view('index/index');  // themes/{theme}/views/index/index.tpl
    }

    public function data($id = null)
    {
        return json_response(['status' => 'success', 'user' => user($id)]);
    }
}
```

### App-level Hooks

Register in `app/Bootstrap.php`:

```php
app()->hook('beforeRequest', function() {
    if( cfg('config', 'maintenance') === 'on' && !is_auth() ) {
        abort('Site under maintenance', 503);
    }
});

app()->hook('afterResponse', function(&$content) {
    $duration = round(microtime(true) - BOSON_START_TIME, 3);
    $content = str_replace('</body>', "<!-- {$duration}s --></body>", $content);
});
```

### CSRF Customization

```php
// Custom checker
app()->csrfChecker(function() {
    return input()->header('X-Custom-Token') === 'secret' ? true : 'Invalid token';
});

// Disable (or csrf_enabled = off in config.ini)
app()->csrfChecker(fn() => true);
```

---

## Input (v2.1)

Single access point for all request data. GET, POST, JSON body, headers, files, cookies. Automatic XSS cleaning.

```php
// Basic access (XSS-cleaned)
$email = input('email');
$all   = input()->all();

input()->filled('email');   // key exists and is not empty
input()->missing('token');   // key missing or empty
```

### Typed Access (no XSS, faster)

```php
$id       = input()->int('id', 0);
$price    = input()->float('price', 0.0);
$active   = input()->bool('active');    // '1'/'true'/'yes'/'on' → true
$name     = input()->string('name');
$tags     = input()->array('tags', []);
$birthday = input()->date('birthday');  // DateTime or null
```

### Separate GET/POST

```php
$page = input()->query('page', 1);   // $_GET only
$body = input()->post('email');      // $_POST only
```

### Request Checks

```php
input()->isJson();          // Content-Type: application/json?
input()->expectsJson();     // AJAX or Accept: /json?
input()->isPost();          // HTTP method?
input()->bearerToken();     // Bearer token from Authorization
```

---

## Validation (v2.1)

Pipe-syntax rules, custom validators, i18n messages.

```php
$validator = validator($data, [
    'email'    => 'required|email|maxlen:255',
    'password' => 'required|minlen:6|confirmed',
    'age'      => 'required|int|min:18|max:99',
    'birthday' => 'date:Y-m-d',
    'tags'     => 'json',
    'slug'     => 'alpha|minlen:3',
]);

if( $validator->fails() ) {
    return json_response(['errors' => $validator->errors()], 422);
}

$clean = $validator->validated();  // only fields with rules
```

**Available rules:** `required`, `nullable`, `trim`, `int`/`integer`, `float`, `bool`/`boolean`, `numeric`, `email`, `url`, `json`, `alpha`, `alphanum`, `date`, `date:Y-m-d`, `min:N`, `max:N`, `minlen:N`, `maxlen:N`, `in:a,b,c`, `not_in:x,y,z`, `same:field`, `confirmed`, `regexp:/.../`, `validator:name`.

---

## Models & Database

Models inherit `Boson\Abstracts\EloquentModel` (extends `Illuminate\Database\Eloquent\Model`). DB config in `app/configs/database.ini`.

```php
class User extends \Boson\Abstracts\EloquentModel
{
    protected $table = 'users';

    public function profile() { return $this->hasOne(Profile::class); }
    public function posts()   { return $this->hasMany(Post::class); }
}
```

**Convenience scopes:** `whereLike($field, $str)`, `whereFulltextMatch($field, $query)`, `orderByRandom()`.

**DB helpers:** `orm()`, `db($connection?)`, `table($table, $conn?)`, `schema($connection?)`.

---

## Themes & Templates

- Theme set in `config.ini` (`theme` key): `theme = "smarty"`
- Engine set via `cover`: `smarty` or `native` (PHTML). Hot-swap — just change the value.
- Templates in `themes/{theme}/views/`
- `view('path/to/view')` — renders with current engine
- `theme()` — returns `Boson\Theme` instance

### Template Variables

```php
theme()->assign('title', 'My Page');
theme()->assign('users', $users);
```

Globals (always available): `{$base_url}`, `{$js_url}`, `{$css_url}`, `{$images_url}`, `{$content_url}`.

### Dynamic CSS/JS

```php
theme()->useThemeCss('extra.css');
theme()->useThemeJs('widget.js', $head = false);  // before </body>
theme()->useExternalJs('https://cdn.example.com/lib.js');
```

Template variables: `{$boson_css}`, `{$boson_js_head}`, `{$boson_js_body}`. Regex injection fallback for legacy templates.

### Smarty Plugins

```smarty
{i18n str="home"}
{num2word number=n words=['year','years']}
```

Custom: `theme()->addPlugin('function', 'name', 'callback')`.

---

## i18n

Translation files: `app/lang/{locale}.php`, return associative array.

```php
// app/lang/ru.php
return ['welcome' => 'Добро пожаловать', 'hello_user' => 'Привет, :name!'];

// Usage
echo i18n('welcome');                          // "Добро пожаловать"
echo i18n()->get('hello_user', ['name' => 'Alex']); // "Привет, Alex!"

// Change locale
i18n()->setLocale('ru');
```

Placeholders use `:name` format, replaced via `strtr`.

---

## Authentication

```php
if (is_auth()) {
    $user = auth()->user();
}

auth()->signin($email, $password, $remember = false);
auth()->signout();
```

Supports session-based auth and long-term "remember me" tokens. Brute-force protection: 5 attempts per 15 minutes per IP/email.

---

## Caching

DB-backed cache via `Boson\TableCache`. Requires `TableCache::install()` before first use.

```php
cache('my_key', $data, 3600);       // store for 1 hour
$data = cache('my_key');            // retrieve
cache('my_key', null);              // delete

// Compute if missing
$data = cacheRemember('stats', fn() => expensiveQuery(), 600);
```

---

## Key Global Helpers

| Function                         | Purpose                                                  |
|----------------------------------|----------------------------------------------------------|
| `cfg($file, $key?, $default?)`   | Read `.ini` config (cached)                              |
| `input($key?, $default?)`        | XSS-cleaned request parameter                            |
| `input()->int($k)` / `bool($k)`  | Typed access without XSS                                 |
| `input()->query($k?)` / `post()` | GET-only / POST-only access                              |
| `input()->isJson()` / `expectsJson()` | Request type checks                                 |
| `session()`                      | Session access                                           |
| `cookies()`                      | Cookie access                                            |
| `router()`                       | MicroRouter v2.1                                         |
| `app()`                          | App instance (hooks, CSRF, lifecycle)                    |
| `i18n($key?)`                    | Translation                                              |
| `view($name, $data?)`            | Template rendering                                       |
| `json_response($data, $code?)`   | JSON response                                            |
| `redirect($url, $status?)`       | HTTP redirect                                            |
| `abort($message, $code?)`        | Terminate with HTTP error                                |
| `auth()` / `is_auth()`           | Authentication                                           |
| `encrypt($str)` / `decrypt($str)`| AES-256-GCM encryption                                   |
| `password_crypt($pass)`          | bcrypt hashing                                           |
| `uuid()`                         | UUID v4 generation                                       |
| `theme()`                        | Theme instance                                           |

---

## Security

- Sensitive data (API keys, DB passwords) stored in `.ini` files inside `app/configs/`.
- **Never commit real credentials.**
- `content/` and `temp/` excluded from git, auto-created if missing.
- Sensitive file access (`*.ini`, `*.php`, `.git/`) blocked via `.htaccess`.
- CSRF enabled by default for all mutating methods. Disable via `config.ini` or `app()->csrfChecker()`.
- XSS filtering on all string parameters. Typed methods (`int`, `float`, `bool`) skip XSS.
- `session.cookie_httponly=1`, `samesite=Strict`, `secure` for HTTPS.
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` — configured in `config.ini`.

---

## Notes

- **Timezone:** `Europe/Moscow` (set in `index.php`)
- **Code style:** PascalCase for classes, snake_case for functions
- **Namespaces:** `Boson\*` for core, `App\*` for app
- **Comments:** mostly in Russian
- **Component versions:** MicroRouter 2.1, Input 2.1, Validator 2.1, Theme 2.1, App 2.1
- Active development, continuously improved

---

## License

MIT License.

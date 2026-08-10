# MicroRouter Documentation (Boson Framework)

**Version:** 2.1

Lightweight HTTP router with middleware pipeline. Supports RESTful methods, named routes, groups, resource controllers, parameter patterns, and fallback routes.

## Registering Routes

```php
// Parameter patterns
router()->whereNumber('id');            // digits [0-9]+
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

// Multiple methods
router()->match(['GET', 'POST'], '/contact', 'ContactController@handle');

// Resource controllers (index, create, store, show, edit, update, destroy)
router()->resource('/photo', 'PhotoController');
```

## Route Parameters

```php
// Required parameter
router()->get('/user/{id}', 'UserController@show');
// URL: /user/123 → UserController@show('123')

// Optional parameter
router()->get('/article/{slug?}', 'ArticleController@show');

// Custom pattern
router()->pattern('id', '\d+');
router()->whereNumber('id');          // shortcut for \d+
router()->whereAlpha('slug');         // shortcut for [a-zA-Z]+
router()->whereAlphaNumeric('slug');  // shortcut for [a-zA-Z0-9]+
```

Default parameter pattern: `[0-9a-zA-Z\-\_]+`

## Groups

```php
router()->group([
    'prefix'     => 'admin',
    'name'       => 'admin',    // dot appended automatically
    'middleware' => ['AuthMiddleware'],
], function($router) {
    $router->get('/dashboard', 'Dashboard@index', 'dashboard');
    // Result: /admin/dashboard, name: admin.dashboard
});
```

Group attributes:
- `prefix` — path prefix
- `name` — name prefix (dot appended automatically)
- `middleware` — middleware applied to all group routes

## Middleware

### Global Middleware
```php
router()->middleware('LoggerMiddleware');
router()->middleware(['StartSession', 'SecurityCheck']);
```

### Route Middleware
```php
router()->get('/profile', [
    'controller' => 'UserController',
    'method'     => 'profile',
    'middleware' => ['AuthMiddleware'],
]);
```

### Writing Middleware
```php
class AuthMiddleware {
    public function handle($route, $next) {
        if (!is_auth()) {
            redirect('/login');
        }
        return $next(); // pass to next middleware or controller
    }
}
```

## Named Routes & URL Generation

```php
// Get URL by name
$url = router()->getPathByName('users.show', ['id' => 5]);
// Result: /users/5

// Redirect by name
router()->redirect('users.show', ['id' => 10]);
```

Names are auto-generated as `snake_case(controller) . '.' . snake_case(method)` if not explicitly provided.

## Fallback Route (404)

```php
router()->fallback('Errors@notFound');
// or with middleware:
router()->fallback(['controller' => 'Errors', 'method' => 'notFound']);
```

Works when `router()->dispatch()` is called directly.

## Dispatch

```php
// Standard — controller is instantiated, _before/action/_after called
$result = router()->dispatch();

// Custom handler — App passes its own controller
$result = router()->dispatch(function() use ($app) {
    // custom logic
});
```

Middleware pipeline wraps around the handler.

## Methods Reference

| Method | Description |
|---|---|
| `get($path, $data, $name?)` | GET route |
| `post($path, $data, $name?)` | POST route |
| `put($path, $data, $name?)` | PUT route |
| `patch($path, $data, $name?)` | PATCH route |
| `delete($path, $data, $name?)` | DELETE route |
| `any($path, $data, $name?)` | Any HTTP method |
| `match($methods, $path, $data, $name?)` | Multiple methods |
| `resource($path, $controller)` | RESTful resource |
| `group($attrs, $callback)` | Route group |
| `middleware($mw)` | Global middleware |
| `fallback($data)` | 404 handler |
| `pattern($key, $pattern)` | Custom parameter regex |
| `whereNumber($name)` | Digits-only param |
| `whereAlpha($name)` | Letters-only param |
| `whereAlphaNumeric($name)` | Letters+digits param |
| `dispatch($handler?)` | Execute matched route |
| `getPathByName($name, $values)` | Generate URL |
| `redirect($name, $values)` | Redirect by name |
| `routes()` / `getAllRoutes()` | List all routes |
| `getRoute()` | Current matched route |
| `getParams()` | Current route parameters |

## Exceptions

`Boson\MicroRouterException` thrown for:
- Invalid request type
- Missing controller/method
- Route not found (without fallback)
- URL generation failure
- Resource controller not implementing the interface

<?php namespace Boson;
/**
 * MicroRouter — лёгкий маршрутизатор для Boson PHP micro framework.
 *
 * Возможности:
 *  - Регистрация маршрутов по HTTP-методам (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD, ANY).
 *  - Группы маршрутов с общими префиксами и middleware.
 *  - Ресурсные контроллеры (resource).
 *  - Именованные маршруты и обратная генерация URL.
 *  - Middleware до и после вызова контроллера.
 *  - Параметры маршрута с пользовательскими паттернами (pattern()).
 *  - Необязательные параметры {id?}.
 *  - Удобные хелперы whereNumber(), whereAlpha(), whereAlphaNumeric().
 *  - Fallback-маршрут для 404.
 *  - Метод dispatch() — запуск найденного маршрута с middleware и хуками.
 *
 * @author  Tishchenko Alexander <info@alex-tisch.ru>
 * @link    http://alex-tisch.ru
 * @version 2.1
 */

use Boson\Traits\SingletonTrait;
use Closure;
use Throwable;

/**
 * Исключение маршрутизатора.
 */
class MicroRouterException extends \Exception {}

class MicroRouter
{
    use SingletonTrait;

    /** Текущий нормализованный запрос (без query и расширений). */
    protected ?string $_request = null;

    /** Все зарегистрированные маршруты. */
    protected array $_routes = [];

    /** Маршруты, сгруппированные по HTTP-методу. */
    protected array $_routes_by_type = [];

    /** Индекс маршрутов по имени для O(1) поиска. */
    protected array $_routes_by_name = [];

    /** Текущий разрешённый маршрут. */
    protected ?array $_current_route = null;

    /** Пользовательские паттерны параметров. */
    protected array $_patterns = [];

    /** Допустимые типы запросов. */
    protected array $_request_types = [
        'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'ANY',
    ];

    /** Расширения, которые нужно срезать в URI. */
    protected array $superfluous = [
        '\.html', '\.htm', '\.php5', '\.php', '\.php3', '\.shtml',
        '\.phtml', '\.dhtml', '\.xhtml', '\.inc', '\.cgi', '\.pl',
        '\.xml', '\.js',
    ];

    /** Стек групп: [['prefix' => '', 'middleware' => [], 'name' => ''], ...] */
    protected array $_group_stack = [];

    /** Глобальные middleware. */
    protected array $_global_middleware = [];

    /** Fallback-маршрут для 404. */
    protected $_fallback_route = null;

    /**
     * Конструктор. Парсит $_SERVER['REQUEST_URI'] и нормализует путь.
     */
    public function __construct()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $parts          = explode('?', $uri, 2);
        $this->_request = preg_replace('/\/+/', '/', $parts[0]);

        if( $this->_request !== '/' ) {
            $this->_request = rtrim($this->_request, '/');
        }

        $superfluous    = implode('|', $this->superfluous);
        $this->_request = preg_replace("/({$superfluous})$/i", '', $this->_request);
    }

    // ---------------------------------------------------------------------
    //  Базовая работа с маршрутами
    // ---------------------------------------------------------------------

    /**
     * Возвращает текущий URI запроса (после нормализации).
     */
    public function getRequestUri(): string
    {
        return $this->_request ?? '/';
    }

    /**
     * Возвращает найденный маршрут или null, если не найден.
     */
    public function getRoute()
    {
        if( !empty($this->_current_route) ) {
            return $this->_current_route;
        }

        $type   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $routes = $this->_routes_by_type[$type] ?? [];

        // HEAD fallback -> GET (стандарт HTTP).
        if( $type === 'HEAD' ) {
            $routes = array_merge($routes, $this->_routes_by_type['GET'] ?? []);
        }

        if( $type !== 'ANY' ) {
            $routes = array_merge($routes, $this->_routes_by_type['ANY'] ?? []);
        }

        foreach($routes as $route) {
            if( preg_match("#{$route['regexp']}#", $this->_request, $matches) ) {
                array_shift($matches); // убираем полное совпадение
                
                $route['params']      = array_values($matches);
                $this->_current_route = $route;

                return $this->_current_route;
            }
        }

        return null;
    }

    /** Возвращает все зарегистрированные маршруты. */
    public function getAllRoutes(): array
    {
        return $this->_routes;
    }

    /** Алиас для getAllRoutes(). */
    public function routes(): array
    {
        return $this->_routes;
    }

    /** Проверяет, существует ли маршрут с указанным именем. */
    public function isRouteNameExists(string $name): bool
    {
        return isset($this->_routes_by_name[$name]);
    }

    /** Возвращает имя контроллера текущего маршрута. */
    public function getControllerName()
    {
        return $this->getRoute()['controller'] ?? null;
    }

    /** Возвращает имя метода текущего маршрута. */
    public function getMethodName()
    {
        return $this->getRoute()['method'] ?? null;
    }

    /** Возвращает параметры текущего маршрута. */
    public function getParams(): array
    {
        return $this->getRoute()['params'] ?? [];
    }

    // ---------------------------------------------------------------------
    //  Регистрация маршрутов по HTTP-методам
    // ---------------------------------------------------------------------

    public function get($path, $data = null, $name = null): self     { return $this->set('GET',     $path, $data, $name); }
    public function post($path, $data = null, $name = null): self    { return $this->set('POST',    $path, $data, $name); }
    public function put($path, $data = null, $name = null): self     { return $this->set('PUT',     $path, $data, $name); }
    public function patch($path, $data = null, $name = null): self   { return $this->set('PATCH',   $path, $data, $name); }
    public function delete($path, $data = null, $name = null): self  { return $this->set('DELETE',  $path, $data, $name); }
    public function options($path, $data = null, $name = null): self { return $this->set('OPTIONS', $path, $data, $name); }
    public function head($path, $data = null, $name = null): self    { return $this->set('HEAD',    $path, $data, $name); }
    public function any($path, $data = null, $name = null): self     { return $this->set('ANY',     $path, $data, $name); }

    /**
     * Регистрирует один маршрут на массив методов.
     *
     * @param array<int, string> $methods
     */
    public function match(array $methods, string $path, $data = null, $name = null): self
    {
        foreach($methods as $m) {
            $this->set($m, $path, $data, $name);
        }
        
        return $this;
    }

    // ---------------------------------------------------------------------
    //  Группы маршрутов
    // ---------------------------------------------------------------------

    /**
     * Создаёт группу маршрутов с общими префиксом/middleware/именным префиксом.
     *
     * @param array{prefix?:string, middleware?:array|string, name?:string} $attributes
     */
    public function group(array $attributes, Closure $callback): self
    {
        $this->_group_stack[] = [
            'prefix'     => trim($attributes['prefix'] ?? '', '/'),
            'middleware' => (array)($attributes['middleware'] ?? []),
            'name'       => $attributes['name'] ?? '',
        ];

        $callback($this);

        array_pop($this->_group_stack);

        return $this;
    }

    /**
     * Добавляет глобальный middleware, применяемый ко всем маршрутам.
     */
    public function middleware($middleware): self
    {
        foreach((array)$middleware as $m) {
            $this->_global_middleware[] = $m;
        }
        
        return $this;
    }

    // ---------------------------------------------------------------------
    //  Ресурсные маршруты
    // ---------------------------------------------------------------------

    /**
     * Регистрирует RESTful-маршруты для контроллера-ресурса.
     */
    public function resource(string $path, string $controller): self
    {
        $className = $this->requireController($controller);
        $obj       = new $className();

        if( !is_subclass_of($obj, '\Boson\Interfaces\Resource') ) {
            throw new MicroRouterException(
                "The specified controller `{$controller}` is not a resource"
            );
        }

        $path            = trim($path, '/');
        $controller_name = snake_case($controller);

        $map = [
            ['GET',    "{$path}",            'index',   "{$controller_name}.index"],
            ['GET',    "{$path}/create",     'create',  "{$controller_name}.create"],
            ['POST',   "{$path}",            'store',   "{$controller_name}.store"],
            ['GET',    "{$path}/{id}",       'show',    "{$controller_name}.show"],
            ['GET',    "{$path}/{id}/edit",  'edit',    "{$controller_name}.edit"],
            ['PUT',    "{$path}/{id}",       'update',  "{$controller_name}.update"],
            ['PATCH',  "{$path}/{id}",       'update',  "{$controller_name}.update.patch"],
            ['DELETE', "{$path}/{id}",       'destroy', "{$controller_name}.destroy"],
        ];

        foreach($map as [$method, $uri, $action, $name]) {
            if( method_exists($obj, $action) ) {
                $this->set($method, $uri, [
                    'controller' => $controller,
                    'method'     => $action,
                    'name'       => $name,
                ]);
            }
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    //  Основной метод регистрации
    // ---------------------------------------------------------------------

    /**
     * Регистрирует маршрут. Поддерживает массив маршрутов (обратная совместимость).
     *
     * @param string $type       HTTP-метод
     * @param string|array $path Путь или массив [[path, data, name?], ...]
     * @param array|string|null $data 'Controller@method' или ['controller'=>..,'method'=>..,'name'=>..,'middleware'=>[]]
     * @param string|null $route_name Имя маршрута (опционально)
     *
     * @throws MicroRouterException
     */
    protected function set($type, $path, $data = null, $route_name = null): self
    {
        $type = strtoupper($type);

        if( !in_array($type, $this->_request_types, true) ) {
            throw new MicroRouterException("Invalid request type specified: {$type}");
        }

        // Массив маршрутов (обратная совместимость)
        if( is_array($path) ) {
            foreach($path as $item) {
                if( empty($item[0]) || empty($item[1]) ) {
                    throw new MicroRouterException(
                        "Incorrect routing array data " . json_encode($item)
                    );
                }
                
                $this->set($type, $item[0], $item[1], $item[2] ?? null);
            }
            
            return $this;
        }

        if( empty($data) ) {
            throw new MicroRouterException(
                "No controller and method for route '{$path}' specified"
            );
        }

        // Преобразование "Controller@method" в массив
        if( is_scalar($data) ) {
            if( !str_contains((string)$data, '@') ) {
                throw new MicroRouterException(
                    "Invalid route action '{$data}'. Expected 'Controller@method'"
                );
            }

            [$controller, $method] = explode('@', (string)$data, 2);

            $data = [
                'controller' => $controller,
                'method'     => $method,
                'name'       => (!empty($route_name) && is_scalar($route_name))
                    ? $route_name
                    : snake_case($controller) . '.' . snake_case($method),
            ];
        }

        if( !is_array($data) || empty($data['controller']) || empty($data['method']) ) {
            throw new MicroRouterException(
                "No controller and method for route '{$path}' specified"
            );
        }

        // Применяем атрибуты группы
        [$path, $data] = $this->applyGroupAttributes($path, $data);

        $path         = trim($path, '/');
        $data['path'] = $path;
        $data['type'] = $type;

        if( empty($data['name']) ) {
            $data['name'] = snake_case($data['controller']) . '.' . snake_case($data['method']);
        }

        // Проверка существования контроллера и метода
        $className = $this->requireController($data['controller']);
        
        if( !method_exists($className, $data['method']) ) {
            throw new MicroRouterException(
                "The specified controller is missing a '{$data['method']}' method"
            );
        }

        $data['regexp']     = $this->buildRegexp($path);
        $data['middleware'] = array_merge(
            $this->_global_middleware,
            $data['middleware'] ?? []
        );

        $this->_routes[]                 = $data;
        $this->_routes_by_type[$type][]  = $data;
        $this->_routes_by_name[$data['name']] = $data;

        return $this;
    }

    /**
     * Применяет атрибуты из активных групп.
     *
     * @return array{0:string, 1:array}
     */
    protected function applyGroupAttributes($path, $data): array
    {
        if( empty($this->_group_stack) ) {
            return [$path, $data];
        }

        $prefix     = '';
        $middleware = [];
        $namePrefix = '';

        foreach($this->_group_stack as $group) {
            if( !empty($group['prefix']) ) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            
            if( !empty($group['middleware']) ) {
                $middleware = array_merge($middleware, $group['middleware']);
            }
            
            if( !empty($group['name']) ) {
                $namePrefix .= $group['name'];
            }
        }

        $path = trim($prefix, '/') . '/' . ltrim($path, '/');

        if( !empty($middleware) ) {
            $data['middleware'] = array_merge($middleware, $data['middleware'] ?? []);
        }

        if( !empty($namePrefix) && !empty($data['name']) ) {
            $data['name'] = (str_ends_with($namePrefix, '.') ? $namePrefix : $namePrefix . '.') . $data['name'];
        }

        return [$path, $data];
    }

    // ---------------------------------------------------------------------
    //  Загрузка контроллеров
    // ---------------------------------------------------------------------

    /**
     * Подключает файл контроллера и возвращает полное имя класса.
     *
     * @throws MicroRouterException
     */
    protected function requireController(string $controller): string
    {
        $className = '\\App\\Controllers\\' . $controller;

        if( class_exists($className) ) {
            return $className;
        }

        $file = ACTIONS_DIR . DIR_SEP . "{$controller}.php";
        
        if( !file_exists($file) ) {
            throw new MicroRouterException("The specified controller {$controller}.php does not exist");
        }

        require_once($file);

        if( !class_exists($className) ) {
            throw new MicroRouterException("Incorrect controller class name for {$controller}");
        }

        return $className;
    }

    // ---------------------------------------------------------------------
    //  Регулярные выражения и параметры
    // ---------------------------------------------------------------------

    /**
     * Строит регулярное выражение из шаблона пути.
     */
    protected function buildRegexp(string $path): string
    {
        // Пользовательские паттерны
        foreach($this->_patterns as $key=>$pattern) {
            $path = preg_replace("/\{{$key}\}/", "({$pattern})", $path);
            $path = preg_replace("/\{{$key}\?\}/", "\/?({$pattern})?", $path);
        }

        // Необязательные параметры {name?}
        $regexp = preg_replace('#/?\{[0-9a-zA-Z_]+\?\}#', '(?:/([0-9a-zA-Z\-\_]+))?', $path);

        // Обязательные параметры {name}
        $regexp = preg_replace('#\{[0-9a-zA-Z_]+\}#', '([0-9a-zA-Z\-\_]+)', $regexp);

        return "^/{$regexp}$";
    }

    /**
     * Задаёт пользовательский regex-паттерн для параметра.
     *
     * @example $router->pattern('id', '\d+');
     */
    public function pattern(string $key, string $pattern): self
    {
        $this->_patterns[ $key ] = $pattern;
        
        return $this;
    }

    /**
     * Параметр принимает только цифры. Сокращение для pattern($name, '[0-9]+').
     */
    public function whereNumber(string $name): self
    {
        return $this->pattern($name, '[0-9]+');
    }

    /**
     * Параметр принимает только буквы. Сокращение для pattern($name, '[a-zA-Z]+').
     */
    public function whereAlpha(string $name): self
    {
        return $this->pattern($name, '[a-zA-Z]+');
    }

    /**
     * Параметр принимает буквы и цифры. Сокращение для pattern($name, '[a-zA-Z0-9]+').
     */
    public function whereAlphaNumeric(string $name): self
    {
        return $this->pattern($name, '[a-zA-Z0-9]+');
    }

    // ---------------------------------------------------------------------
    //  Генерация URL и редиректы
    // ---------------------------------------------------------------------

    /**
     * Редирект на маршрут по имени.
     */
    public function redirect(string $name, array $values = []): void
    {
        redirect($this->getPathByName($name, $values));
    }

    /**
     * Возвращает путь маршрута по имени, подставляя значения параметров.
     *
     * @throws MicroRouterException
     */
    public function getPathByName(string $name, array $values = []): string
    {
        if( !isset($this->_routes_by_name[$name]) ) {
            throw new MicroRouterException("Invalid route name `{$name}`");
        }

        $route = $this->_routes_by_name[$name];
        $path  = '/' . ltrim($route['path'], '/');

        foreach($values as $key=>$val) {
            $path = str_replace(
                ["{{$key}}", "{{$key}?}"],
                (string)$val,
                $path
            );
        }

        // Удаляем неиспользованные необязательные параметры
        $path = preg_replace('#/?\{[0-9a-zA-Z_]+\?\}#', '', $path);

        // Если остались необязательные параметры — ошибка
        if( preg_match('#\{[0-9a-zA-Z_]+\}#', $path) ) {
            throw new MicroRouterException(
                "Missing required parameters for route `{$name}`"
            );
        }

        return $path;
    }

    // ---------------------------------------------------------------------
    //  Fallback-маршрут
    // ---------------------------------------------------------------------

    /**
     * Регистрирует fallback-маршрут, вызываемый когда ни один маршрут не совпал.
     * Принимает строку 'Controller@method' или массив ['controller' => ..., 'method' => ...].
     */
    public function fallback($data): self
    {
        $this->_fallback_route = $data;
        return $this;
    }

    // ---------------------------------------------------------------------
    //  Dispatch
    // ---------------------------------------------------------------------

    /**
     * Находит маршрут и вызывает его контроллер с middleware и хуками _before/_after.
     * Возвращает результат работы метода контроллера.
     *
     * @param callable|null $handler Пользовательский обработчик. Если null — создаётся
     *                               стандартный: инстанциирует контроллер, вызывает
     *                               _before → метод → _after.
     *
     * @throws MicroRouterException
     */
    public function dispatch($handler = null)
    {
        $route = $this->getRoute();
        
        if( $route === null ) {
            if( !empty($this->_fallback_route) ) {
                $route = $this->resolveFallback();
            } else {
                throw new MicroRouterException("Route not found for URI: {$this->_request}");
            }
        }

        if( $handler === null ) {
            $handler = function() use ($route) {
                $className = $this->requireController($route['controller']);
                $obj       = new $className();

                if( method_exists($obj, '_before') ) {
                    $obj->_before();
                }

                $params = $route['params'] ?? [];
                $result = count($params) > 0
                    ? call_user_func_array([$obj, $route['method']], $params)
                    : call_user_func([$obj, $route['method']]);

                if( method_exists($obj, '_after') ) {
                    $obj->_after();
                }

                return $result;
            };
        }

        // Оборачиваем middleware (pipeline)
        $middleware = array_reverse($route['middleware'] ?? []);
        
        foreach($middleware as $m) {
            $next    = $handler;
            $handler = function () use ($m, $next, $route) {
                $callable = is_string($m) && class_exists($m) ? [new $m(), 'handle'] : $m;
                return $callable($route, $next);
            };
        }

        return $handler();
    }

    /**
     * Преобразует fallback-данные в структуру маршрута.
     */
    protected function resolveFallback(): array
    {
        $data = $this->_fallback_route;

        if( is_scalar($data) ) {
            if( !str_contains((string)$data, '@') ) {
                throw new MicroRouterException("Invalid fallback route '{$data}'");
            }
            [$controller, $method] = explode('@', (string)$data, 2);
            $data = [
                'controller' => $controller,
                'method'     => $method,
            ];
        }

        return [
            'controller' => $data['controller'],
            'method'     => $data['method'],
            'params'     => [],
            'middleware' => $this->_global_middleware,
        ];
    }
}

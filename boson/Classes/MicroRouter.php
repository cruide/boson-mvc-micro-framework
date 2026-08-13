<?php namespace Boson;
/**
 * MicroRouter — lightweight router for Boson PHP micro framework.
 *
 * Features:
 *  - Route registration by HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD, ANY).
 *  - Route groups with common prefixes and middleware.
 *  - Resource controllers (resource).
 *  - Named routes and reverse URL generation.
 *  - Middleware before and after calling the controller.
 *  - Route parameters with custom patterns (pattern()).
 *  - Optional parameters {id?}.
 *  - Convenient helpers whereNumber(), whereAlpha(), whereAlphaNumeric().
 *  - Fallback route for 404.
 *  - dispatch() method — runs the found route with middleware and hooks.
 *
 * @author  Tishchenko Alexander <info@alex-tisch.ru>
 * @link    http://alex-tisch.ru
 * @version 2.1
 */

use Boson\Traits\SingletonTrait;
use Closure;
use Throwable;

/**
 * Router exception.
 */
class MicroRouterException extends \Exception {}

class MicroRouter
{
    use SingletonTrait;

    /** Current normalized request (without query and extensions). */
    protected ?string $_request = null;

    /** All registered routes. */
    protected array $_routes = [];

    /** Routes grouped by HTTP method. */
    protected array $_routes_by_type = [];

    /** Route index by name for O(1) lookup. */
    protected array $_routes_by_name = [];

    /** Current resolved route. */
    protected ?array $_current_route = null;

    /** Custom parameter patterns. */
    protected array $_patterns = [];

    /** Allowed request types. */
    protected array $_request_types = [
        'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'ANY',
    ];

    /** Extensions to strip from the URI. */
    protected array $superfluous = [
        '\.html', '\.htm', '\.php5', '\.php', '\.php3', '\.shtml',
        '\.phtml', '\.dhtml', '\.xhtml', '\.inc', '\.cgi', '\.pl',
        '\.xml', '\.js',
    ];

    /** Group stack: [['prefix' => '', 'middleware' => [], 'name' => ''], ...] */
    protected array $_group_stack = [];

    /** Global middleware. */
    protected array $_global_middleware = [];

    /** Fallback route for 404. */
    protected $_fallback_route = null;

    /**
     * Constructor. Parses $_SERVER['REQUEST_URI'] and normalizes the path.
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
    //  Basic route handling
    // ---------------------------------------------------------------------

    /**
     * Returns the current request URI (after normalization).
     */
    public function getRequestUri(): string
    {
        return $this->_request ?? '/';
    }

    /**
     * Returns the found route or null if not found.
     */
    public function getRoute()
    {
        if( !empty($this->_current_route) ) {
            return $this->_current_route;
        }

        $type   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $routes = $this->_routes_by_type[$type] ?? [];

        // HEAD fallback -> GET (HTTP standard).
        if( $type === 'HEAD' ) {
            $routes = array_merge($routes, $this->_routes_by_type['GET'] ?? []);
        }

        if( $type !== 'ANY' ) {
            $routes = array_merge($routes, $this->_routes_by_type['ANY'] ?? []);
        }

        foreach($routes as $route) {
            if( preg_match("#{$route['regexp']}#", $this->_request, $matches) ) {
                array_shift($matches); // remove the full match
                
                $route['params']      = array_values($matches);
                $this->_current_route = $route;

                return $this->_current_route;
            }
        }

        return null;
    }

    /** Returns all registered routes. */
    public function getAllRoutes(): array
    {
        return $this->_routes;
    }

    /** Alias for getAllRoutes(). */
    public function routes(): array
    {
        return $this->_routes;
    }

    /** Checks whether a route with the specified name exists. */
    public function isRouteNameExists(string $name): bool
    {
        return isset($this->_routes_by_name[$name]);
    }

    /** Returns the controller name of the current route. */
    public function getControllerName()
    {
        return $this->getRoute()['controller'] ?? null;
    }

    /** Returns the method name of the current route. */
    public function getMethodName()
    {
        return $this->getRoute()['method'] ?? null;
    }

    /** Returns the parameters of the current route. */
    public function getParams(): array
    {
        return $this->getRoute()['params'] ?? [];
    }

    // ---------------------------------------------------------------------
    //  Route registration by HTTP methods
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
     * Registers a single route for an array of methods.
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
    //  Route groups
    // ---------------------------------------------------------------------

    /**
     * Creates a route group with a common prefix/middleware/name prefix.
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
     * Adds global middleware applied to all routes.
     */
    public function middleware($middleware): self
    {
        foreach((array)$middleware as $m) {
            $this->_global_middleware[] = $m;
        }
        
        return $this;
    }

    // ---------------------------------------------------------------------
    //  Resource routes
    // ---------------------------------------------------------------------

    /**
     * Registers RESTful routes for a resource controller.
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
    //  Main registration method
    // ---------------------------------------------------------------------

    /**
     * Registers a route. Supports an array of routes (backward compatibility).
     *
     * @param string $type       HTTP method
     * @param string|array $path Path or array [[path, data, name?], ...]
     * @param array|string|null $data 'Controller@method' or ['controller'=>..,'method'=>..,'name'=>..,'middleware'=>[]]
     * @param string|null $route_name Route name (optional)
     *
     * @throws MicroRouterException
     */
    protected function set($type, $path, $data = null, $route_name = null): self
    {
        $type = strtoupper($type);

        if( !in_array($type, $this->_request_types, true) ) {
            throw new MicroRouterException("Invalid request type specified: {$type}");
        }

        // Array of routes (backward compatibility)
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

        // Convert "Controller@method" to an array
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

        // Apply group attributes
        [$path, $data] = $this->applyGroupAttributes($path, $data);

        $path         = trim($path, '/');
        $data['path'] = $path;
        $data['type'] = $type;

        if( empty($data['name']) ) {
            $data['name'] = snake_case($data['controller']) . '.' . snake_case($data['method']);
        }

        // Check controller and method existence
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
     * Applies attributes from active groups.
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
    //  Controller loading
    // ---------------------------------------------------------------------

    /**
     * Includes the controller file and returns the fully qualified class name.
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
    //  Regular expressions and parameters
    // ---------------------------------------------------------------------

    /**
     * Builds a regular expression from a path template.
     */
    protected function buildRegexp(string $path): string
    {
        // Custom patterns
        foreach($this->_patterns as $key=>$pattern) {
            $path = preg_replace("/\{{$key}\}/", "({$pattern})", $path);
            $path = preg_replace("/\{{$key}\?\}/", "\/?({$pattern})?", $path);
        }

        // Optional parameters {name?}
        $regexp = preg_replace('#/?\{[0-9a-zA-Z_]+\?\}#', '(?:/([0-9a-zA-Z\-\_]+))?', $path);

        // Required parameters {name}
        $regexp = preg_replace('#\{[0-9a-zA-Z_]+\}#', '([0-9a-zA-Z\-\_]+)', $regexp);

        return "^/{$regexp}$";
    }

    /**
     * Sets a custom regex pattern for a parameter.
     *
     * @example $router->pattern('id', '\d+');
     */
    public function pattern(string $key, string $pattern): self
    {
        $this->_patterns[ $key ] = $pattern;
        
        return $this;
    }

    /**
     * The parameter accepts only digits. Shorthand for pattern($name, '[0-9]+').
     */
    public function whereNumber(string $name): self
    {
        return $this->pattern($name, '[0-9]+');
    }

    /**
     * The parameter accepts only letters. Shorthand for pattern($name, '[a-zA-Z]+').
     */
    public function whereAlpha(string $name): self
    {
        return $this->pattern($name, '[a-zA-Z]+');
    }

    /**
     * The parameter accepts letters and digits. Shorthand for pattern($name, '[a-zA-Z0-9]+').
     */
    public function whereAlphaNumeric(string $name): self
    {
        return $this->pattern($name, '[a-zA-Z0-9]+');
    }

    // ---------------------------------------------------------------------
    //  URL generation and redirects
    // ---------------------------------------------------------------------

    /**
     * Redirect to a route by name.
     */
    public function redirect(string $name, array $values = []): void
    {
        redirect($this->getPathByName($name, $values));
    }

    /**
     * Returns the route path by name, substituting parameter values.
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

        // Remove unused optional parameters
        $path = preg_replace('#/?\{[0-9a-zA-Z_]+\?\}#', '', $path);

        // If optional parameters remain — error
        if( preg_match('#\{[0-9a-zA-Z_]+\}#', $path) ) {
            throw new MicroRouterException(
                "Missing required parameters for route `{$name}`"
            );
        }

        return $path;
    }

    // ---------------------------------------------------------------------
    //  Fallback route
    // ---------------------------------------------------------------------

    /**
     * Registers a fallback route, called when no route matched.
     * Accepts a 'Controller@method' string or an array ['controller' => ..., 'method' => ...].
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
     * Finds the route and calls its controller with middleware and _before/_after hooks.
     * Returns the result of the controller method.
     *
     * @param callable|null $handler Custom handler. If null — a standard one is created:
     *                               it instantiates the controller, calls
     *                               _before → method → _after.
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

        // Wrap middleware (pipeline)
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
     * Converts fallback data into a route structure.
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

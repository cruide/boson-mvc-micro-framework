<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
 * Central request dispatcher. Manages the request lifecycle:
 * initialization → routing → controller → rendering.
 *
 * Supports:
 * - application-level hooks (beforeRequest, afterResponse)
 * - a custom CSRF checker
 * - enabling/disabling CSRF via config
 * - handling of unhandled exceptions
 * - debug mode (debug = on in config.ini)
 * - middleware pipeline via router()->dispatch()
*/

use Boson\Traits\SingletonTrait;

class AppException extends \Exception {}

class App
{
    use SingletonTrait;

    /** @var object Current controller */
    protected $controller;

    /** @var array Application-level hooks: ['beforeRequest' => [callable, ...], ...] */
    protected $_hooks = [];

    /** @var callable|null Custom CSRF checker */
    protected $_csrfChecker = null;

    public function __construct()
    {
        orm();
        i18n();
        input();
        theme();
        session();
        cookies();

        if( !($routing = router()->getRoute()) ) {
            abort('Not found...', 404);
        }

        $className = '\\App\\Controllers\\' . $routing['controller'];

        try {
            $this->controller = new $className();
            
        } catch( AppException $e ) {
            abort( $e->getMessage() );
        }
    }

    /**
     * Returns the current controller instance.
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Registers an application-level hook.
     *
     * Available events:
     * - `beforeRequest` — before the controller is called
     * - `afterResponse`  — after the controller is called, before rendering (receives &$content)
     *
     * @param string $event    Event name
     * @param callable $callback Handler function
     * @return $this
     */
    public function hook(string $event, callable $callback): self
    {
        $this->_hooks[$event][] = $callback;
        return $this;
    }

    /**
     * Sets a custom CSRF checker.
     *
     * The callback must return true (check passed) or a string with an error message.
     * Pass null to reset to the standard check.
     *
     * @param callable|null $checker
     * @return $this
     */
    public function csrfChecker(?callable $checker): self
    {
        $this->_csrfChecker = $checker;
        return $this;
    }
    
    /**
     * Main request execution method.
     * Wraps the entire lifecycle in try/catch to handle any exceptions.
     */
    public function execute()
    {
        try {
            $this->runRequest();
            
        } catch( \Throwable $e ) {
            $this->handleException($e);
        }
    }

    /**
     * Request lifecycle:
     * CORS → beforeRequest hooks → CSRF → _before → controller → _after → afterResponse hooks → rendering
     */
    protected function runRequest()
    {
        $method = router()->getMethodName();
        $params = router()->getParams();

        cors();

        $this->runHooks('beforeRequest');

        $this->checkCsrf();

        // Run the controller via dispatch() — this is how the middleware pipeline works
        $content = router()->dispatch(function() use ($method, $params) {

            if( method_exists($this->controller, '_before') ) {
                $this->controller->_before();
            }

            try {
                $result = (array_count($params) > 0)
                    ? call_user_func_array([$this->controller, $method], $params)
                    : call_user_func([$this->controller, $method]);

            } catch( AppException $e ) {
                abort( $e->getMessage(), 500 );
            }

            if( method_exists($this->controller, '_after') ) {
                $this->controller->_after();
            }

            return $result;
        });

        $this->runHooks('afterResponse', [&$content]);

        try {
            theme()->display( $content );
            
        } catch( AppException $e ) {
            abort( $e->getMessage(), 500 );
        }
    }

    /**
     * Executes all registered hooks for an event.
     */
    protected function runHooks(string $event, array $args = []): void
    {
        foreach($this->_hooks[$event] ?? [] as $cb) {
            $cb(...$args);
        }
    }

    /**
     * CSRF token check for mutating methods.
     * Can be disabled via config: csrf_enabled = off
     * Can be replaced via csrfChecker().
     */
    protected function checkCsrf(): void
    {
        // Disabled in config
        $enabled = cfg('config', 'csrf_enabled');
        if( $enabled === 'off' || $enabled === '0' || $enabled === 'false' ) {
            return;
        }

        // Only for mutating methods
        if( !input()->isPost() && !input()->isPut() && !input()->isPatch() && !input()->isDelete() ) {
            return;
        }

        // Custom checker
        if( $this->_csrfChecker !== null ) {
            $result = ($this->_csrfChecker)();
            if( $result !== true ) {
                $msg = is_string($result) ? $result : 'CSRF token mismatch';
                $this->abortCsrf($msg);
            }
            return;
        }

        // Standard check
        if( !function_exists('csrf_verify') || !csrf_verify() ) {
            $this->abortCsrf('CSRF token mismatch');
        }
    }

    /**
     * Aborts the request with a CSRF error.
     */
    protected function abortCsrf(string $message): void
    {
        if( input()->expectsJson() ) {
            abort_json(['message' => $message, 'code' => 419], 419);
        }
        abort($message, 419);
    }

    /**
     * Handler for unhandled exceptions.
     * In debug mode shows details, in production — a generic message.
     */
    protected function handleException(\Throwable $e): void
    {
        if( $e instanceof AppException ) {
            abort($e->getMessage(), 500);
        }

        // Log everything
        error_log((string)$e);

        $debug = cfg('config', 'debug');
        if( $debug === 'on' || $debug === '1' || $debug === 'true' ) {
            $msg = get_class($e) . ': ' . $e->getMessage()
                 . ' in ' . $e->getFile() . ':' . $e->getLine();
        } else {
            $msg = 'Internal server error';
        }

        abort($msg, 500);
    }
}

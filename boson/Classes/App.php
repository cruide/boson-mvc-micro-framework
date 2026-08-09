<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Центральный диспетчер запроса. Управляет жизненным циклом:
* инициализация → маршрутизация → контроллер → рендеринг.
*
* Поддерживает:
* - хуки уровня приложения (beforeRequest, afterResponse)
* - кастомный CSRF-проверяющий
* - отключение/включение CSRF через конфиг
* - обработку необработанных исключений
* - режим отладки (debug = on в config.ini)
* - middleware pipeline через router()->dispatch()
*/

use Boson\Traits\SingletonTrait;

class AppException extends \Exception {}

class App
{
    use SingletonTrait;

    /** @var object Текущий контроллер */
    protected $controller;

    /** @var array Хуки уровня приложения: ['beforeRequest' => [callable, ...], ...] */
    protected $_hooks = [];

    /** @var callable|null Кастомный CSRF-проверяющий */
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
     * Возвращает текущий экземпляр контроллера.
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Регистрирует хук уровня приложения.
     *
     * Доступные события:
     * - `beforeRequest` — перед вызовом контроллера
     * - `afterResponse`  — после вызова контроллера, до рендеринга (получает &$content)
     *
     * @param string $event    Имя события
     * @param callable $callback Функция-обработчик
     * @return $this
     */
    public function hook(string $event, callable $callback): self
    {
        $this->_hooks[$event][] = $callback;
        return $this;
    }

    /**
     * Устанавливает кастомный CSRF-проверяющий.
     *
     * Callback должен вернуть true (проверка пройдена) или строку с сообщением об ошибке.
     * Передайте null чтобы сбросить на стандартную проверку.
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
     * Главный метод выполнения запроса.
     * Оборачивает весь жизненный цикл в try/catch для обработки любых исключений.
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
     * Жизненный цикл запроса:
     * CORS → хуки beforeRequest → CSRF → _before → контроллер → _after → хуки afterResponse → рендеринг
     */
    protected function runRequest()
    {
        $method = router()->getMethodName();
        $params = router()->getParams();

        cors();

        $this->runHooks('beforeRequest');

        $this->checkCsrf();

        // Запускаем контроллер через dispatch() — так работает middleware pipeline
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
     * Выполняет все зарегистрированные хуки для события.
     */
    protected function runHooks(string $event, array $args = []): void
    {
        foreach($this->_hooks[$event] ?? [] as $cb) {
            $cb(...$args);
        }
    }

    /**
     * Проверка CSRF-токена для мутирующих методов.
     * Можно отключить через конфиг: csrf_enabled = off
     * Можно заменить через csrfChecker().
     */
    protected function checkCsrf(): void
    {
        // Отключено в конфиге
        $enabled = cfg('config', 'csrf_enabled');
        if( $enabled === 'off' || $enabled === '0' || $enabled === 'false' ) {
            return;
        }

        // Только для мутирующих методов
        if( !input()->isPost() && !input()->isPut() && !input()->isPatch() && !input()->isDelete() ) {
            return;
        }

        // Кастомный проверяющий
        if( $this->_csrfChecker !== null ) {
            $result = ($this->_csrfChecker)();
            if( $result !== true ) {
                $msg = is_string($result) ? $result : 'CSRF token mismatch';
                $this->abortCsrf($msg);
            }
            return;
        }

        // Стандартная проверка
        if( !function_exists('csrf_verify') || !csrf_verify() ) {
            $this->abortCsrf('CSRF token mismatch');
        }
    }

    /**
     * Прерывает запрос с ошибкой CSRF.
     */
    protected function abortCsrf(string $message): void
    {
        if( input()->expectsJson() ) {
            abort_json(['message' => $message, 'code' => 419], 419);
        }
        abort($message, 419);
    }

    /**
     * Обработчик необработанных исключений.
     * В debug-режиме показывает детали, в production — общее сообщение.
     */
    protected function handleException(\Throwable $e): void
    {
        if( $e instanceof AppException ) {
            abort($e->getMessage(), 500);
        }

        // Логируем всё
        error_log((string)$e);

        $debug = cfg('config', 'debug');
        if( $debug === 'on' || $debug === '1' || $debug === 'true' ) {
            $msg = get_class($e) . ': ' . $e->getMessage()
                 . ' in ' . $e->getFile() . ':' . $e->getLine();
        } else {
            $msg = 'Внутренняя ошибка сервера';
        }

        abort($msg, 500);
    }
}

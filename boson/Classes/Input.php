<?php namespace Boson;
/**
 * @name      Boson PHP framework
 * @author    Tishchenko Alexander (info@alex-tisch.ru)
 * @copyright Copyright (c) 2018-2024 All rights reserved
 * @version   2.1
 *
 * Класс для безопасной работы с входными данными HTTP-запроса.
 * Унифицирует доступ к GET/POST/PUT/PATCH/DELETE/JSON параметрам,
 * заголовкам, файлам и cookie. Предоставляет XSS-защиту,
 * валидацию ключей, типизированный доступ и множество helper-методов.
 */

use Boson\Traits\SingletonTrait;
use Boson\Abstracts\Registry;

final class Input extends Registry
{
    use SingletonTrait;

    /**
     * Кешированный Bearer токен
     * false = пытались извлечь, но не нашли
     * null  = ещё не извлекали
     */
    protected $bearer = null;

    /**
     * HTTP-метод запроса (кеш)
     */
    protected $method = null;

    /**
     * HTTP-заголовки запроса (кеш)
     *
     * @var array<string, string>|null
     */
    protected $headersCache = null;

    /**
     * "Сырые" данные запроса (php://input) (кеш)
     */
    protected $rawInput = null;

    /**
     * Распарсенное JSON-тело запроса (кеш)
     */
    protected $jsonPayload = null;

    /**
     * Данные из PUT/PATCH/DELETE (кеш)
     */
    protected $methodPayload = null;

    /**
     * Строгий режим валидации ключей.
     * Если true — при обнаружении недопустимых символов в ключе
     * вызывается abort() (поведение старой версии).
     * Если false — такие ключи просто пропускаются.
     */
    protected $strictKeyValidation = false;

    /**
     * Кеш XSS-очищенных значений.
     *
     * @var array<string, mixed>
     */
    protected $xss_cache = [];

    /**
     * Конструктор: собирает входные данные из всех источников.
     */
    public function __construct()
    {
        $this->properties['headers'] = [];

        // Строгий режим из конфига
        if( function_exists('cfg') ) {
            $strict = cfg('config', 'input_strict_key_validation');
            $this->strictKeyValidation = ($strict === 'on' || $strict === '1' || $strict === 'true');
        }

        // GET
        $this->ingest($_GET ?? []);

        // POST
        $this->ingest($_POST ?? []);

        // PUT/PATCH/DELETE тело (application/x-www-form-urlencoded or JSON)
        $this->ingest($this->getMethodPayload());

        // Предварительно извлекаем Bearer (сохраняем обратную совместимость)
        $this->getBearerToken();
    }

    /**
     * Загружает данные массива в хранилище, очищая ключи и значения.
     *
     * @param array<string|int, mixed> $data
     */
    protected function ingest($data)
    {
        if( empty($data) ) {
            return;
        }

        foreach($data as $key=>$val) {
            $cleanKey = $this->_clean_key((string)$key);

            if( $cleanKey === null ) {
                continue;
            }

            $this->properties[ $cleanKey ] = $this->_clean_val($val);
        }
    }

    /* -------------------------------------------------------------------------
     * Публичный API — базовый доступ
     * ---------------------------------------------------------------------- */

    /**
     * Возвращает все входные данные в виде массива.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * Получить входное значение по имени (с XSS-очисткой).
     *
     * @param string     $name    Имя параметра
     * @param mixed|null $default Значение по умолчанию
     */
    public function input($name, $default = null)
    {
        $value = $this->getClean($name);
        
        return $value ?? $default;
    }

    /**
     * Проверка наличия заполненного значения (не null, не пустая строка, не пустой массив).
     */
    public function filled($name)
    {
        if( !$this->has($name) ) {
            return false;
        }

        $value = $this->properties[ $name ];

        if( is_array($value) ) {
            return count($value) > 0;
        }

        return $value !== null && $value !== '';
    }

    /**
     * Обратное к filled — значение отсутствует или пустое.
     */
    public function missing($name): bool
    {
        return !$this->filled($name);
    }

    /**
     * Вернуть только указанные ключи.
     *
     * @param string[]|string $keys
     * @return array<string, mixed>
     */
    public function only($keys): array
    {
        $keys   = is_array($keys) ? $keys : func_get_args();
        $result = [];

        foreach($keys as $key) {
            if( $this->has($key) ) {
                $result[ $key ] = $this->get($key);
            }
        }

        return $result;
    }

    /**
     * Вернуть все данные, кроме указанных ключей.
     *
     * @param string[]|string $keys
     * @return array<string, mixed>
     */
    public function except($keys): array
    {
        $keys   = is_array($keys) ? $keys : func_get_args();
        $result = $this->toArray();

        foreach($keys as $key) {
            unset($result[$key]);
        }

        return $result;
    }

    /* -------------------------------------------------------------------------
     * Типизированный доступ (без XSS, специально для чисел/булевых/дат)
     * ---------------------------------------------------------------------- */

    /**
     * Получить значение как целое число.
     */
    public function int($name, $default = 0): int
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (int)$this->properties[ $name ];
    }

    /**
     * Получить значение как число с плавающей точкой.
     */
    public function float($name, $default = 0.0): float
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (float)$this->properties[ $name ];
    }

    /**
     * Получить значение как булево.
     * Строки '1', 'true', 'yes', 'on' → true.
     * Строки '0', 'false', 'no', 'off', '' → false.
     * Остальное — обычное (bool) приведение.
     */
    public function bool($name, $default = false): bool
    {
        if( !$this->has($name) ) {
            return $default;
        }

        $value = $this->properties[ $name ];

        if( is_bool($value) ) {
            return $value;
        }

        if( is_string($value) ) {
            $lower = strtolower($value);

            if( in_array($lower, ['1', 'true', 'yes', 'on'], true) ) {
                return true;
            }

            if( in_array($lower, ['0', 'false', 'no', 'off', ''], true) ) {
                return false;
            }
        }

        return (bool)$value;
    }

    /**
     * Получить значение как строку (с XSS-очисткой).
     */
    public function string($name, $default = ''): string
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (string)$this->getClean($name);
    }

    /**
     * Получить значение как массив.
     */
    public function array($name, $default = []): array
    {
        if( !$this->has($name) ) {
            return $default;
        }

        $value = $this->properties[ $name ];

        return is_array($value) ? $value : $default;
    }

    /**
     * Получить значение как объект DateTime.
     *
     * @param string      $name    Имя параметра
     * @param string|null $format  Формат даты (например 'Y-m-d'). Если null — strtotime.
     * @param mixed|null  $default Значение по умолчанию
     * @return \DateTime|null
     */
    public function date($name, $format = null, $default = null)
    {
        if( !$this->filled($name) ) {
            return $default;
        }

        $value = (string)$this->properties[ $name ];

        if( $format !== null ) {
            $dt = \DateTime::createFromFormat($format, $value);
            return ($dt !== false) ? $dt : $default;
        }

        try {
            return new \DateTime($value);
        } catch( \Throwable $e ) {
            return $default;
        }
    }

    /* -------------------------------------------------------------------------
     * GET и POST раздельно
     * ---------------------------------------------------------------------- */

    /**
     * Получить значение только из GET-параметров (с XSS-очисткой).
     *
     * @param string|null $key     Имя параметра или null для всего массива
     * @param mixed|null  $default Значение по умолчанию
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        if( $key === null ) {
            $result = [];

            foreach(($_GET ?? []) as $k => $v) {
                $cleanKey = $this->_clean_key((string)$k);

                if( $cleanKey !== null ) {
                    $result[ $cleanKey ] = $this->_xss_clean($this->_clean_val($v));
                }
            }

            return $result;
        }

        if( !array_key_exists($key, $_GET ?? []) ) {
            return $default;
        }

        return $this->_xss_clean($this->_clean_val($_GET[ $key ]));
    }

    /**
     * Получить значение только из POST-параметров (с XSS-очисткой).
     *
     * @param string|null $key     Имя параметра или null для всего массива
     * @param mixed|null  $default Значение по умолчанию
     * @return mixed
     */
    public function post($key = null, $default = null)
    {
        if( $key === null ) {
            $result = [];

            foreach(($_POST ?? []) as $k => $v) {
                $cleanKey = $this->_clean_key((string)$k);

                if( $cleanKey !== null ) {
                    $result[ $cleanKey ] = $this->_xss_clean($this->_clean_val($v));
                }
            }

            return $result;
        }

        if( !array_key_exists($key, $_POST ?? []) ) {
            return $default;
        }

        return $this->_xss_clean($this->_clean_val($_POST[ $key ]));
    }

    /* -------------------------------------------------------------------------
     * JSON / Content-Type helpers
     * ---------------------------------------------------------------------- */

    /**
     * Проверяет, является ли запрос JSON.
     */
    public function isJson(): bool
    {
        $contentType = (string)$this->header('Content-Type', '');

        return stripos($contentType, 'application/json') !== false;
    }

    /**
     * Проверяет, ожидает ли клиент JSON-ответ.
     * True если AJAX или Accept содержит /json или +json.
     */
    public function expectsJson(): bool
    {
        if( $this->isAjax() ) {
            return true;
        }

        $accept = (string)$this->header('Accept', '');

        return stripos($accept, '/json') !== false || stripos($accept, '+json') !== false;
    }

    /**
     * Получить Bearer токен из заголовка Authorization.
     *
     * @return string|false Токен или false, если не найден
     */
    public function getBearerToken()
    {
        if( $this->bearer !== null ) {
            return $this->bearer;
        }

        $header = $this->header('Authorization');

        if( !empty($header) && preg_match('/Bearer\s+(.*)/', trim($header), $matches) ) {
            $this->bearer = !empty($matches[1]) ? $matches[1] : false;
            
        } else {
            $this->bearer = false;
        }

        return $this->bearer;
    }

    /**
     * Alias для getBearerToken() в современном стиле.
     */
    public function bearerToken()
    {
        return $this->getBearerToken();
    }

    /* -------------------------------------------------------------------------
     * HTTP-метод
     * ---------------------------------------------------------------------- */

    /**
     * Возвращает HTTP-метод запроса (в верхнем регистре).
     */
    public function method()
    {
        if( $this->method !== null ) {
            return $this->method;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);

        // Поддержка method override
        if( $method === 'POST' ) {
            $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
                     ?? ($_POST['_method'] ?? null);

            if( $override !== null ) {
                $override = strtoupper((string)$override);

                if( in_array($override, ['PUT', 'PATCH', 'DELETE'], true) ) {
                    $method = $override;
                }
            }
        }

        return $this->method = $method;
    }

    /**
     * Проверить совпадение метода (регистр не важен).
     */
    public function isMethod($name): bool
    {
        return $this->method() === strtoupper($name);
    }

    public function isGet(): bool    { return $this->isMethod('GET'); }
    public function isPost(): bool   { return $this->isMethod('POST'); }
    public function isPut(): bool    { return $this->isMethod('PUT'); }
    public function isPatch(): bool  { return $this->isMethod('PATCH'); }
    public function isDelete(): bool { return $this->isMethod('DELETE'); }
    public function isHead(): bool   { return $this->isMethod('HEAD'); }
    public function isOptions(): bool { return $this->isMethod('OPTIONS'); }

    /**
     * AJAX-запрос?
     */
    public function isAjax(): bool
    {
        $requested = $this->header('X-Requested-With');
        
        return $requested !== null && strcasecmp($requested, 'XMLHttpRequest') === 0;
    }

    /**
     * HTTPS?
     */
    public function isSecure(): bool
    {
        if( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ) {
            return true;
        }
        
        if( ($_SERVER['SERVER_PORT'] ?? null) == 443 ) {
            return true;
        }
        
        if( ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https' ) {
            return true;
        }

        return false;
    }

    /* -------------------------------------------------------------------------
     * Заголовки
     * ---------------------------------------------------------------------- */

    /**
     * Получить все HTTP-заголовки.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if( $this->headersCache !== null ) {
            return $this->headersCache;
        }

        $headers = [];

        if( function_exists('apache_request_headers') ) {
            $raw = apache_request_headers();

            if( is_array($raw) ) {
                foreach($raw as $key => $val) {
                    $headers[ $this->normalizeHeaderName((string)$key) ] = (string)$val;
                }
            }
        }

        // Фоллбек / дополнение из $_SERVER (HTTP_*)
        foreach($_SERVER as $key => $val) {
            if( str_starts_with((string)$key, 'HTTP_') ) {
                $name = $this->normalizeHeaderName(substr($key, 5));

                if( !isset($headers[$name]) ) {
                    $headers[ $name ] = (string)$val;
                }
            }
        }

        // Отдельные "не HTTP_*" заголовки
        foreach(['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'] as $key) {
            if( isset($_SERVER[$key]) ) {
                $headers[ $this->normalizeHeaderName($key) ] = (string)$_SERVER[ $key ];
            }
        }

        // Authorization header (некоторые серверы прячут его)
        if( !isset($headers['Authorization']) ) {
            if( isset($_SERVER['Authorization']) ) {
                $headers['Authorization'] = (string)$_SERVER['Authorization'];
                
            } else if( isset($_SERVER['HTTP_AUTHORIZATION']) ) {
                $headers['Authorization'] = (string)$_SERVER['HTTP_AUTHORIZATION'];
                
            } else if( isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ) {
                $headers['Authorization'] = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        return $this->headersCache = $headers;
    }

    /**
     * Получить конкретный HTTP-заголовок.
     *
     * @param string     $name    Имя заголовка (регистронезависимо)
     * @param mixed|null $default
     */
    public function header($name, $default = null)
    {
        $name    = $this->normalizeHeaderName($name);
        $headers = $this->headers();

        // Точное совпадение по нормализованному имени
        if( isset($headers[$name]) ) {
            return $headers[ $name ];
        }

        // Регистронезависимый поиск
        foreach($headers as $hKey => $hVal) {
            if( strcasecmp($hKey, $name) === 0 ) {
                return $hVal;
            }
        }

        return $default;
    }

    /**
     * Нормализует имя заголовка: HTTP_X_REQUESTED_WITH => X-Requested-With.
     */
    protected function normalizeHeaderName($name)
    {
        $name = str_replace(['_', '-'], ' ', strtolower($name));
        $name = ucwords($name);
        
        return str_replace(' ', '-', $name);
    }

    /* -------------------------------------------------------------------------
     * Cookies / Server / IP / UA
     * ---------------------------------------------------------------------- */

    /**
     * Получить значение cookie.
     */
    public function cookie($name, $default = null)
    {
        return $_COOKIE[ $name ] ?? $default;
    }

    /**
     * Получить значение из $_SERVER.
     */
    public function server($name, $default = null)
    {
        return $_SERVER[ $name ] ?? $default;
    }

    /**
     * Получить IP-адрес клиента (с учётом прокси).
     */
    public function ip()
    {
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach($candidates as $key) {
            if( empty($_SERVER[$key]) ) {
                continue;
            }

            foreach(explode(',', (string)$_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if( filter_var($ip, FILTER_VALIDATE_IP) !== false ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * User-Agent клиента.
     */
    public function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * URI запроса.
     */
    public function uri()
    {
        return (string)($_SERVER['REQUEST_URI'] ?? '/');
    }

    /**
     * Путь без query string.
     */
    public function path()
    {
        $uri  = $this->uri();
        $qPos = strpos($uri, '?');
        
        return $qPos === false ? $uri : substr($uri, 0, $qPos);
    }

    /* -------------------------------------------------------------------------
     * Файлы
     * ---------------------------------------------------------------------- */

    /**
     * Получить массив описания загруженного файла.
     *
     * @return array<string, mixed>|null
     */
    public function file($name)
    {
        return isset($_FILES[$name]) ? $_FILES[$name] : null;
    }

    /**
     * Проверка наличия успешно загруженного файла.
     */
    public function hasFile($name): bool
    {
        $f = $this->file($name);

        if( $f === null ) {
            return false;
        }

        // Массив файлов (multi upload)
        if( is_array($f['error'] ?? null) ) {
            foreach($f['error'] as $err) {
                if( $err === UPLOAD_ERR_OK ) {
                    return true;
                }
            }
            
            return false;
        }

        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * Все файлы запроса.
     *
     * @return array<string, array<string, mixed>>
     */
    public function files(): array
    {
        return $_FILES ?? [];
    }

    /* -------------------------------------------------------------------------
     * Raw / JSON / Method payload
     * ---------------------------------------------------------------------- */

    /**
     * Получить "сырые" данные из php://input.
     */
    public function raw()
    {
        if( $this->rawInput !== null ) {
            return $this->rawInput;
        }

        $data = @file_get_contents('php://input');
        
        return $this->rawInput = $data === false ? '' : $data;
    }

    /**
     * Получить JSON-тело запроса в виде массива.
     *
     * @param string|null $key     Опциональный ключ внутри JSON
     * @param mixed|null  $default Значение по умолчанию
     */
    public function json($key = null, $default = null)
    {
        if( $this->jsonPayload === null ) {
            $contentType = (string)$this->header('Content-Type', '');

            if( stripos($contentType, 'application/json') !== false ) {
                $decoded = json_decode($this->raw(), true);
                
                $this->jsonPayload = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : false;
                    
            } else {
                $this->jsonPayload = false;
            }
        }

        if( $this->jsonPayload === false ) {
            return $key === null ? [] : $default;
        }

        if( $key === null ) {
            return $this->jsonPayload;
        }

        return $this->jsonPayload[ $key ] ?? $default;
    }

    /**
     * Возвращает данные из тела PUT/PATCH/DELETE (form-urlencoded или JSON).
     *
     * @return array<string, mixed>
     */
    protected function getMethodPayload(): array
    {
        if( $this->methodPayload !== null ) {
            return $this->methodPayload;
        }

        $method = $this->method();

        if( !in_array($method, ['PUT', 'PATCH', 'DELETE'], true) ) {
            return $this->methodPayload = [];
        }

        $raw = @file_get_contents('php://input');

        if( !is_string($raw) || $raw === '' ) {
            return $this->methodPayload = [];
        }

        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        if( stripos($contentType, 'application/json') !== false ) {
            $decoded = json_decode($raw, true);
            
            return $this->methodPayload = (is_array($decoded)) ? $decoded : [];
        }

        $parsed = [];
        
        parse_str($raw, $parsed);
        
        return $this->methodPayload = $parsed;
    }

    /* -------------------------------------------------------------------------
     * Внутренние методы очистки (обратная совместимость)
     * ---------------------------------------------------------------------- */

    /**
     * Получение значения с XSS-очисткой (с кэшированием).
     */
    protected function getClean($name = null)
    {
        if( $name === null || !array_key_exists($name, $this->properties) ) {
            return null;
        }

        if( array_key_exists($name, $this->xss_cache) ) {
            return $this->xss_cache[ $name ];
        }

        return $this->xss_cache[ $name ] = $this->_xss_clean($this->properties[ $name ]);
    }

    /**
     * Валидация ключа. Допустимы: буквы, цифры, _ - / : .
     *
     * В мягком режиме возвращает null для невалидных ключей,
     * в строгом — вызывает abort().
     */
    protected function _clean_key($str)
    {
        if( !preg_match('/^[a-z0-9:_\.\/\-]+$/i', $str) ) {
            if( $this->strictKeyValidation && function_exists('abort') ) {
                abort("Your request {$str} contains disallowed characters.");
            }
            
            return null;
        }

        return $str;
    }

    /**
     * Рекурсивная очистка значений: нормализация переносов строк.
     */
    protected function _clean_val($val)
    {
        if( is_array($val) ) {
            $result = [];

            foreach($val as $key => $item) {
                $cleanKey = $this->_clean_key((string)$key);

                if( $cleanKey === null ) {
                    continue;
                }

                $result[ $cleanKey ] = $this->_clean_val($item);
            }

            return $result;
        }

        if( !is_scalar($val) && $val !== null ) {
            // Объекты и ресурсы — не трогаем (безопаснее вернуть как есть)
            return $val;
        }

        return preg_replace('/\015\012|\015|\012/', "\n", (string)$val);
    }

    /**
     * XSS-фильтрация строки или массива.
     */
    protected function _xss_clean($data)
    {
        if( is_array($data) ) {
            $result = [];
            
            foreach($data as $key => $val) {
                $result[ $key ] = $this->_xss_clean($val);
            }
            
            return $result;
        }

        if( !is_scalar($data) ) {
            return $data;
        }

        $data = (string)$data;

        // Fix &entity\n;
        $data = str_replace(
            ['&amp;', '&lt;', '&gt;'],
            ['&amp;amp;', '&amp;lt;', '&amp;gt;'],
            $data
        );
        
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

        // Удаление атрибутов on* и xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

        // javascript:/vbscript:/-moz-binding:
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu',
            '$1=$2nojavascript...',
            $data
        );
        
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu',
            '$1=$2novbscript...',
            $data
        );
        
        $data = preg_replace(
            '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u',
            '$1=$2nomozbinding...',
            $data
        );

        // IE expression/behaviour/script
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace(
            '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu',
            '$1>',
            $data
        );

        // Namespaced elements
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

        // Нежелательные теги
        do {
            $old  = $data;
            $data = preg_replace(
                '#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i',
                '',
                $data
            );
            
        } while ($old !== $data);

        return $data;
    }

    /* -------------------------------------------------------------------------
     * Настройки
     * ---------------------------------------------------------------------- */

    /**
     * Включить/выключить строгую валидацию ключей.
     */
    public function setStrictKeyValidation($strict): self
    {
        $this->strictKeyValidation = $strict;
        
        return $this;
    }
}

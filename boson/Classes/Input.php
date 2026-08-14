<?php namespace Boson;
/**
 * @name      Boson PHP framework
 * @author    Tishchenko Alexander (info@alex-tisch.ru)
 * @copyright Copyright (c) 2018-2024 All rights reserved
 * @version   2.1
 *
 * Class for safe handling of HTTP request input data.
 * Unifies access to GET/POST/PUT/PATCH/DELETE/JSON parameters,
 * headers, files and cookies. Provides XSS protection,
 * key validation, typed access and many helper methods.
 */

use Boson\Traits\SingletonTrait;
use Boson\Abstracts\Registry;

final class Input extends Registry
{
    use SingletonTrait;

    /**
     * Cached Bearer token
     * false = tried to extract, but not found
     * null  = not extracted yet
     */
    protected $bearer = null;

    /**
     * HTTP request method (cache)
     */
    protected $method = null;

    /**
     * HTTP request headers (cache)
     *
     * @var array<string, string>|null
     */
    protected $headersCache = null;

    /**
     * "Raw" request data (php://input) (cache)
     */
    protected $rawInput = null;

    /**
     * Parsed JSON request body (cache)
     */
    protected $jsonPayload = null;

    /**
     * Data from PUT/PATCH/DELETE (cache)
     */
    protected $methodPayload = null;

    /**
     * Strict key validation mode.
     * If true — when disallowed characters are detected in a key
     * abort() is called (old version behavior).
     * If false — such keys are simply skipped.
     */
    protected $strictKeyValidation = false;

    /**
     * Cache of XSS-cleaned values.
     *
     * @var array<string, mixed>
     */
    protected $xss_cache = [];

    /**
     * Constructor: collects input data from all sources.
     */
    public function __construct()
    {
        $this->properties['headers'] = [];

        // Strict mode from config
        if( function_exists('cfg') ) {
            $strict = cfg('config', 'input_strict_key_validation');
            $this->strictKeyValidation = ($strict === 'on' || $strict === '1' || $strict === 'true');
        }

        // GET
        $this->ingest($_GET ?? []);

        // POST
        $this->ingest($_POST ?? []);

        // PUT/PATCH/DELETE body (application/x-www-form-urlencoded or JSON)
        $this->ingest($this->getMethodPayload());

        // Pre-extract Bearer (preserve backward compatibility)
        $this->getBearerToken();
    }

    /**
     * Loads array data into storage, cleaning keys and values.
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
     * Public API — basic access
     * ---------------------------------------------------------------------- */

    /**
     * Returns all input data as-is (without XSS cleaning).
     *
     * Values are returned raw so that sensitive fields (passwords, JSON,
     * structured data) are not corrupted. Escape on output, not on input.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->properties;
    }

    /**
     * Get an input value by name (with XSS cleaning).
     *
     * @param string     $name    Parameter name
     * @param mixed|null $default Default value
     */
    public function input($name, $default = null)
    {
        $value = $this->getClean($name);
        
        return $value ?? $default;
    }

    /**
     * Checks for a filled value (not null, not an empty string, not an empty array).
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
     * Opposite of filled — the value is missing or empty.
     */
    public function missing($name): bool
    {
        return !$this->filled($name);
    }

    /**
     * Return only the specified keys.
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
     * Return all data except the specified keys.
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
     * Typed access (no XSS, specifically for numbers/booleans/dates)
     * ---------------------------------------------------------------------- */

    /**
     * Get a value as an integer.
     */
    public function int($name, $default = 0): int
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (int)$this->properties[ $name ];
    }

    /**
     * Get a value as a float.
     */
    public function float($name, $default = 0.0): float
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (float)$this->properties[ $name ];
    }

    /**
     * Get a value as a boolean.
     * Strings '1', 'true', 'yes', 'on' → true.
     * Strings '0', 'false', 'no', 'off', '' → false.
     * Everything else — normal (bool) casting.
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
     * Get a value as a string (with XSS cleaning).
     */
    public function string($name, $default = ''): string
    {
        if( !$this->has($name) ) {
            return $default;
        }

        return (string)$this->getClean($name);
    }

    /**
     * Get a value as an array.
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
     * Get a value as a DateTime object.
     *
     * @param string      $name    Parameter name
     * @param string|null $format  Date format (e.g. 'Y-m-d'). If null — strtotime.
     * @param mixed|null  $default Default value
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
     * GET and POST separately
     * ---------------------------------------------------------------------- */

    /**
     * Get a value only from GET parameters (with XSS cleaning).
     *
     * @param string|null $key     Parameter name or null for the entire array
     * @param mixed|null  $default Default value
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
     * Get a value only from POST parameters (with XSS cleaning).
     *
     * @param string|null $key     Parameter name or null for the entire array
     * @param mixed|null  $default Default value
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
     * Checks whether the request is JSON.
     */
    public function isJson(): bool
    {
        $contentType = (string)$this->header('Content-Type', '');

        return stripos($contentType, 'application/json') !== false;
    }

    /**
     * Checks whether the client expects a JSON response.
     * True if AJAX or Accept contains /json or +json.
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
     * Get the Bearer token from the Authorization header.
     *
     * @return string|false Token or false if not found
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
     * Modern-style alias for getBearerToken().
     */
    public function bearerToken()
    {
        return $this->getBearerToken();
    }

    /* -------------------------------------------------------------------------
     * HTTP method
     * ---------------------------------------------------------------------- */

    /**
     * Returns the HTTP request method (uppercase).
     */
    public function method()
    {
        if( $this->method !== null ) {
            return $this->method;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);

        // Method override support
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
     * Check the method match (case-insensitive).
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
     * AJAX request?
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
     * Headers
     * ---------------------------------------------------------------------- */

    /**
     * Get all HTTP headers.
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

        // Fallback / supplement from $_SERVER (HTTP_*)
        foreach($_SERVER as $key => $val) {
            if( str_starts_with((string)$key, 'HTTP_') ) {
                $name = $this->normalizeHeaderName(substr($key, 5));

                if( !isset($headers[$name]) ) {
                    $headers[ $name ] = (string)$val;
                }
            }
        }

        // Separate "non-HTTP_*" headers
        foreach(['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'] as $key) {
            if( isset($_SERVER[$key]) ) {
                $headers[ $this->normalizeHeaderName($key) ] = (string)$_SERVER[ $key ];
            }
        }

        // Authorization header (some servers hide it)
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
     * Get a specific HTTP header.
     *
     * @param string     $name    Header name (case-insensitive)
     * @param mixed|null $default
     */
    public function header($name, $default = null)
    {
        $name    = $this->normalizeHeaderName($name);
        $headers = $this->headers();

        // Exact match by normalized name
        if( isset($headers[$name]) ) {
            return $headers[ $name ];
        }

        // Case-insensitive search
        foreach($headers as $hKey => $hVal) {
            if( strcasecmp($hKey, $name) === 0 ) {
                return $hVal;
            }
        }

        return $default;
    }

    /**
     * Normalizes a header name: HTTP_X_REQUESTED_WITH => X-Requested-With.
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
     * Get a cookie value.
     */
    public function cookie($name, $default = null)
    {
        return $_COOKIE[ $name ] ?? $default;
    }

    /**
     * Get a value from $_SERVER.
     */
    public function server($name, $default = null)
    {
        return $_SERVER[ $name ] ?? $default;
    }

    /**
     * Get the client IP address (taking proxies into account).
     */
    public function ip()
    {
        // Only REMOTE_ADDR is trusted by default; forwarded headers are
        // honored only when the request comes from a configured trusted proxy.
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;

        if( !empty($remote) && function_exists('cfg') ) {
            $trusted = cfg('config', 'trusted_proxies');

            if( !empty($trusted) ) {
                $trusted = array_map('trim', explode(',', (string)$trusted));

                if( in_array($remote, $trusted, true) ) {
                    $candidates = [
                        'HTTP_X_FORWARDED_FOR',
                        'HTTP_CLIENT_IP',
                        'HTTP_X_FORWARDED',
                        'HTTP_X_CLUSTER_CLIENT_IP',
                        'HTTP_FORWARDED_FOR',
                        'HTTP_FORWARDED',
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
                }
            }
        }

        if( !empty($remote) && filter_var($remote, FILTER_VALIDATE_IP) !== false ) {
            return $remote;
        }

        return '0.0.0.0';
    }

    /**
     * Client User-Agent.
     */
    public function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Request URI.
     */
    public function uri()
    {
        return (string)($_SERVER['REQUEST_URI'] ?? '/');
    }

    /**
     * Path without the query string.
     */
    public function path()
    {
        $uri  = $this->uri();
        $qPos = strpos($uri, '?');
        
        return $qPos === false ? $uri : substr($uri, 0, $qPos);
    }

    /* -------------------------------------------------------------------------
     * Files
     * ---------------------------------------------------------------------- */

    /**
     * Get the description array of an uploaded file.
     *
     * @return array<string, mixed>|null
     */
    public function file($name)
    {
        return isset($_FILES[$name]) ? $_FILES[$name] : null;
    }

    /**
     * Checks for the presence of a successfully uploaded file.
     */
    public function hasFile($name): bool
    {
        $f = $this->file($name);

        if( $f === null ) {
            return false;
        }

        // Array of files (multi upload)
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
     * All files of the request.
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
     * Get the "raw" data from php://input.
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
     * Get the JSON request body as an array.
     *
     * @param string|null $key     Optional key inside JSON
     * @param mixed|null  $default Default value
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
     * Returns data from the PUT/PATCH/DELETE body (form-urlencoded or JSON).
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
     * Internal cleaning methods (backward compatibility)
     * ---------------------------------------------------------------------- */

    /**
     * Get a value with XSS cleaning (with caching).
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
     * Key validation. Allowed: letters, digits, _ - / : .
     *
     * In soft mode returns null for invalid keys,
     * in strict mode calls abort().
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
     * Recursive value cleaning: line break normalization.
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
            // Objects and resources — leave untouched (safer to return as-is)
            return $val;
        }

        return preg_replace('/\015\012|\015|\012/', "\n", (string)$val);
    }

    /**
     * XSS filtering of a string or array.
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

        // Remove on* and xmlns attributes
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

        // Unwanted tags
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
     * Settings
     * ---------------------------------------------------------------------- */

    /**
     * Enable/disable strict key validation.
     */
    public function setStrictKeyValidation($strict): self
    {
        $this->strictKeyValidation = $strict;
        
        return $this;
    }
}

# Global Functions Reference (Boson Framework)

`boson/Functions.php` — 70+ globally available helper functions.

## Paths & Files

| Function | Description |
|---|---|
| `path_correct($dir, $end_slash?)` | Normalize path separators |
| `get_filename($path)` | Filename from full path |
| `get_file_extension($filename)` | Extension without dot |
| `generate_file_name($ext)` | Random filename: `timestamp_hash.ext` |
| `file_put_gz_content($file, $data, $level?)` | Write with gzip |
| `file_get_gz_content($file)` | Read gzip with decompression |
| `gz_file_pack($in, $out?)` | Pack existing file to `.gz` |
| `is_image($path)` | Check if file is an image |
| `get_image_extension($path)` | Detect extension by MIME type |

## Arrays

| Function | Description |
|---|---|
| `array_count($arr, $mode?)` | Safe `count()` — returns 0 for non-arrays |
| `array_key_isset($key, $arr)` | Key exists (even with null value) |
| `array_get_first($arr, $key?)` | First element (or `[key=>value]`) |
| `array_get_first_key($arr)` | Key of first element |
| `array_get_last($arr, $key?)` | Last element |
| `array_get_last_key($arr)` | Key of last element |
| `trim_array($items)` | Recursive trim |
| `obj_to_array($obj)` | Recursive object → array |

## Validation

| Function | Description |
|---|---|
| `is_email($str)` | Email via `filter_var` |
| `is_url($str)` | URL via `filter_var` |
| `is_alphanum($str)` | Letters (lat/cyr) + digits |
| `is_alpha($str)` | Letters only |
| `is_date($str)` | Format `DD.MM.YYYY` |
| `is_name($str)` | Letters, digits, spaces, apostrophe |
| `is_variable_name($str)` | Valid PHP variable name |
| `is_action_name($str)` | Valid action name |
| `is_uuid($uuid)` | UUID v4 validation |
| `is_ipaddress($str)` | IP address validation |
| `is_url_exists($url)` | URL responds (via `get_headers()`) |
| `is_ajax()` | AJAX request check |

## Strings

| Function | Description |
|---|---|
| `escape($str, $method?)` | `htmlspecialchars` / `htmlentities` (recursive) |
| `unescape($str)` | Reverse escaping (recursive) |
| `studly_case($val)` | `hello_world` → `HelloWorld` |
| `camel_case($val)` | `hello_world` → `helloWorld` |
| `snake_case($val, $delim?, $spaces?)` | `HelloWorld` → `hello_world` |
| `str_ucfirst($s)` | Alias: `Str::ucfirst()` |
| `str_lower($s)` | Alias: `Str::lower()` |
| `str_upper($s)` | Alias: `Str::upper()` |
| `str_length($s)` | Alias: `Str::length()` |

## Cryptography

| Function | Description |
|---|---|
| `password_crypt($pass)` | bcrypt via `password_hash(PASSWORD_BCRYPT)` |
| `password_verify_legacy($pass, $hash)` | Legacy MD5+crypt check |
| `password_generate($len?)` | Random password |
| `encrypt($data, $key?)` | AES-256-GCM encryption (key via `boson_encryption_key()`) |
| `decrypt($data, $key?)` | Decrypt (AES-256-GCM + RC4 fallback) |
| `str_base64_encrypt($s, $key?, $gz?)` | Encrypt + base64 (+ gzip) |
| `str_base64_decrypt($s, $key?, $gz?)` | Reverse |
| `salt_generation($len?)` | Salt: `sha1(random_bytes(32))` |
| `uuid()` | UUID v4 |

## HTTP

| Function | Description |
|---|---|
| `redirect($url?, $status?)` | HTTP redirect (URL string, array, or home) |
| `abort($msg, $code?)` | Terminate with error template |
| `abort_json($data, $code?)` | JSON error response |
| `json_response($data, $status?)` | JSON response (disables layout) |
| `http_cache_off()` | Cache-disabling headers |
| `send_header_app_info()` | `X-Boson-Time` and `X-Boson-Memory` headers |
| `cors()` | CORS headers |
| `execution_timeout($sec?)` | Set `max_execution_time` |

## Date & Time

| Function | Description |
|---|---|
| `boson_date_format($str, $fmt?, $def?, $fmt?)` | Date formatting with strftime support |
| `boson_make_timestamp($str)` | String/DateTime to Unix timestamp |
| `carbon($dt?, $fmt?)` | Carbon helper |
| `date_week_name($ts, $full?)` | Day name (Russian) |
| `get_month_by_num($n)` | Month name (Russian) |
| `make_path_from_date($ts?)` | `YYYY/MM/DD` path |

## Numbers & Math

| Function | Description |
|---|---|
| `float_extract($num)` | Split float to parts |
| `percentage($total, $val)` | Percentage |
| `num2word($n, $words)` | Plural form: `num2word(5, ['year','years'])` → `'years'` |
| `rating_stars($n)` | Stars: `★★★★` |
| `in_radius($px, $py, $cx, $cy, $cr)` | Point in circle check |

## Other

| Function | Description |
|---|---|
| `cfg($name, $key?, $default?)` | Read `.ini` config (cached) |
| `view($tpl?, $vars?)` | Render template |
| `theme($name?)` | Theme instance / switch |
| `get_ip_address()` | Client IP (proxy-aware) |
| `get_http_status($code)` | Status text: `404` → `'404 Not Found'` |
| `make_url($url)` | Absolute URL |
| `controller_exists($name)` | Check if controller file exists |
| `make_controller($name, $before?)` | Instantiate controller |
| `memory_clear()` | Garbage collection |
| `get_mem_use($max?, $round?)` | Memory usage in MB |

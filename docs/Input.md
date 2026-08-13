# Input Class Documentation (Boson Framework)

**Version:** 2.1

Single access point for all HTTP request data. Unifies GET, POST, PUT/PATCH/DELETE body, JSON body, headers, files, and cookies. Automatic XSS cleaning. Typed access methods.

## Basic Access

```php
$email = input('email');              // XSS-cleaned value
$limit = input('limit', 10);          // with default

$all = input()->all();                // all parameters (XSS-cleaned)

input()->filled('email');             // key exists and is not empty
input()->missing('token');            // key missing or empty
input()->has('id');                   // key exists (even if null)

$subset = input()->only(['name', 'email']);
$rest   = input()->except(['password']);
```

## Typed Access (no XSS, faster)

```php
$id       = input()->int('id', 0);          // (int)
$price    = input()->float('price', 0.0);   // (float)
$active   = input()->bool('active');        // '1'/'true'/'yes'/'on' → true
$name     = input()->string('name');        // (string) with XSS
$tags     = input()->array('tags', []);     // (array)
$birthday = input()->date('birthday');      // DateTime or null
$birthday = input()->date('birthday', 'Y-m-d'); // with format
```

`bool()` recognizes: `'1'`, `'true'`, `'yes'`, `'on'` as `true`; `'0'`, `'false'`, `'no'`, `'off'`, `''` as `false`.

## Separate GET/POST

```php
$page    = input()->query('page', 1);   // $_GET only
$allGet  = input()->query();            // all GET params

$email   = input()->post('email');      // $_POST only
$allPost = input()->post();             // all POST params
```

## Request Checks

```php
input()->method();         // 'GET', 'POST', 'PUT'...
input()->isPost();         // true if POST
input()->isGet();          // true if GET
input()->isPut();          // true if PUT
input()->isPatch();        // true if PATCH
input()->isDelete();       // true if DELETE
input()->isHead();         // true if HEAD
input()->isOptions();      // true if OPTIONS
input()->isAjax();         // X-Requested-With: XMLHttpRequest
input()->isJson();         // Content-Type: application/json
input()->expectsJson();    // AJAX or Accept: /json
input()->isSecure();       // HTTPS (including X-Forwarded-Proto)
```

## Headers

```php
input()->header('Content-Type');       // single header
input()->header('X-Custom', 'default');
input()->headers();                    // all headers (array)

input()->bearerToken();                // Bearer token from Authorization
```

## JSON & Raw Body

```php
input()->json();                       // whole JSON body as array
input()->json('user_id', 0);           // specific key

input()->raw();                        // raw php://input
```

## Files

```php
input()->hasFile('avatar');            // file uploaded successfully?
$file = input()->file('avatar');       // $_FILES array

input()->files();                      // all files
```

## Client Info

```php
input()->ip();          // client IP (checks 7 proxy headers)
input()->userAgent();   // User-Agent string
input()->uri();         // full URI with query string
input()->path();        // path without query string
input()->cookie('name', 'default');
input()->server('DOCUMENT_ROOT');
```

## Method Override

Supports `X-HTTP-Method-Override` header and `_method` POST field. A POST with `_method=PUT` is treated as PUT for both method detection and CSRF check.

## Security

- All string values from `input('key')`, `string()`, `query()`, `post()` pass through `_xss_clean()` — removes `on*` attributes, `javascript:` URIs, dangerous tags.
- `int()`, `float()`, `bool()`, `array()`, `date()` skip XSS (unnecessary for non-string types).
- XSS results are cached — repeated reads of the same key don't re-run the filter.
- Key validation: only `[a-z0-9:_\.\/\-]` allowed. Invalid keys are silently skipped (or abort in strict mode).
- Strict mode via config: `input_strict_key_validation = on` in `config.ini`.

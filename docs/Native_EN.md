# Native Class Documentation (Boson Framework)

**Version:** 2.1

Lightweight PHTML template engine. Smarty-compatible API for easy migration.

## Initialization

```php
$tpl = new Native('/path/to/templates/');
// Throws NativeException if directory doesn't exist
```

## Template Variables

### Local (`assign`)

```php
$tpl->assign('title', 'Home');
$tpl->assign(['user' => 'Alex', 'role' => 'admin']); // bulk
$tpl->assign('key', null);                            // remove
```

### Global (`setGlobal`)

Static — available across all instances.

```php
Native::setGlobal('site_name', 'My Site');
Native::setGlobal(['version' => '1.0']);
Native::setGlobal('old', null);  // remove
$tpl->assignGlobal('key', 'val'); // instance alias
```

## Rendering

### `fetch($file_name, $need_ext?, $xhtml?)`

Renders template and returns string.

| Parameter | Default | Description |
|---|---|---|
| `$file_name` | — | Template filename |
| `$need_ext` | `true` | Auto-append `.phtml` |
| `$xhtml` | `XHTML_CRRECTION_OFF` | XHTML correction |

```php
$html = $tpl->fetch('index');              // → index.phtml
$html = $tpl->fetch('custom.tpl', false);  // without auto-extension
```

### `display($file_name, $xhtml?)`

Renders and outputs directly. Sends `X-Boson-*` headers.

## Variable Management

```php
$tpl->remove('name');       // remove single variable
$tpl->flushProperties();    // clear all local variables
```

## XHTML Correction

Fixes: unquoted attributes, unclosed tags (`<br>`, `<img>`, `<hr>`), extra whitespace.

```php
// Per-call:
$tpl->fetch('page', true, Native::XHTML_CRRECTION_ON);

// Global:
Native::$correctXHTML = true;
```

## Constants

| Constant | Value |
|---|---|
| `EXTENSION` | `'phtml'` |
| `XHTML_CRRECTION_ON` | `true` |
| `XHTML_CRRECTION_OFF` | `false` |

## Security

- `extract()` uses `EXTR_SKIP` — template variables cannot override existing variables (including superglobals).
- Never pass user input directly into template filenames without sanitization.

## Dependencies

- `path_correct()` — path normalization
- `send_header_app_info()` — for `display()` method

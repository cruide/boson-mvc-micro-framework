# Str Class Documentation (Boson Framework)

Static string helper methods. All UTF-8 aware via `mbstring`, with `iconv` (windows-1251) and single-byte fallbacks.

## Methods

### `Str::ucfirst($str): string`
Uppercase first letter (multibyte).
```php
Str::ucfirst('hello'); // "Hello"
```

### `Str::lower($str): string`
Lowercase (multibyte).
```php
Str::lower('HELLO'); // "hello"
```

### `Str::upper($str): string`
Uppercase (multibyte).
```php
Str::upper('hello'); // "HELLO"
```

### `Str::length($str): int`
String length in characters (multibyte).
```php
Str::length('hello'); // 5
```

### `Str::strstr($haystack, $needle, $part?): string|false`
Find substring (multibyte `strstr`).
```php
Str::strstr('hello@example.com', '@'); // "@example.com"
```

### `Str::contains($haystack, $needle): bool`
Check if string contains substring.
```php
Str::contains('hello world', 'world'); // true
```

### `Str::startsWith($haystack, $needle): bool`
Check if string starts with substring.
```php
Str::startsWith('https://site.ru', 'https'); // true
```

### `Str::endsWith($haystack, $needle): bool`
Check if string ends with substring.
```php
Str::endsWith('file.pdf', '.pdf'); // true
```

### `Str::crop($string, $length?): string`
Truncate to nearest word boundary within limit (adds `...` if longer).
```php
Str::crop('A long text for demonstration of truncation', 20);
// "A long text for..."
```

### `Str::truncate($string, $length?, $etc?, $break_words?, $middle?): string`
Truncate with ellipsis. Supports middle truncation (`$middle=true`).
```php
Str::truncate('verylongfilename.txt', 15, '…', false, true);
// "verylong…name.txt"
```

## Global Aliases

Defined in `Functions.php`:

| Function | Equivalent |
|---|---|
| `str_ucfirst($s)` | `Str::ucfirst($s)` |
| `str_lower($s)` | `Str::lower($s)` |
| `str_upper($s)` | `Str::upper($s)` |
| `str_length($s)` | `Str::length($s)` |

## Usage in Framework

- **Validator** — `Str::length()` for `minlen`/`maxlen`, `Str::ucfirst()` for error messages
- **PHTML Themes** — `str_ucfirst()` in `layout.phtml`

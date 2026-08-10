# Документация класса `Str` (Boson Framework)

Набор статических методов для работы со строками. Все методы поддерживают UTF-8 через `mbstring`, с fallback на `iconv` (windows-1251) и однобайтовые функции.

## Методы

### `Str::ucfirst($str): string`
Заглавная первая буква (multibyte).
```php
Str::ucfirst('привет'); // "Привет"
```

### `Str::lower($str): string`
Нижний регистр (multibyte).
```php
Str::lower('ПРИВЕТ'); // "привет"
```

### `Str::upper($str): string`
Верхний регистр (multibyte).
```php
Str::upper('привет'); // "ПРИВЕТ"
```

### `Str::length($str): int`
Длина строки в символах (multibyte).
```php
Str::length('привет'); // 6
```

### `Str::strstr($haystack, $needle, $part?): string|false`
Поиск подстроки (multibyte-аналог `strstr`).
```php
Str::strstr('hello@example.com', '@'); // "@example.com"
```

### `Str::contains($haystack, $needle): bool`
Содержит ли строка подстроку.
```php
Str::contains('hello world', 'world'); // true
```

### `Str::startsWith($haystack, $needle): bool`
Начинается ли строка с подстроки.
```php
Str::startsWith('https://site.ru', 'https'); // true
```

### `Str::endsWith($haystack, $needle): bool`
Заканчивается ли строка на подстроку.
```php
Str::endsWith('file.pdf', '.pdf'); // true
```

### `Str::crop($string, $length?): string`
Обрезка до ближайшего пробела в пределах лимита (с `...` если длиннее).
```php
Str::crop('Длинный текст для примера обрезки строки', 20);
// "Длинный текст для..."
```

### `Str::truncate($string, $length?, $etc?, $break_words?, $middle?): string`
Обрезка строки с многоточием. Поддерживает обрезку с середины (`$middle=true`).
```php
Str::truncate('оченьдлинноеимяфайла.txt', 15, '…', false, true);
// "оченьд…айла.txt"
```

## Глобальные алиасы

В `Functions.php` определены короткие функции:

| Функция | Аналог |
|---|---|
| `str_ucfirst($s)` | `Str::ucfirst($s)` |
| `str_lower($s)` | `Str::lower($s)` |
| `str_upper($s)` | `Str::upper($s)` |
| `str_length($s)` | `Str::length($s)` |

## Использование в проекте

- **Validator** — `Str::length()` для `minlen`/`maxlen`, `Str::ucfirst()` для сообщений об ошибках
- **Темы (PHTML)** — `str_ucfirst()` в `layout.phtml`
- **EloquentModel** — не использует напрямую

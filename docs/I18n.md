# I18n Class Documentation (Boson Framework)

Internationalization system. Singleton, loads translations from PHP files, supports placeholders and fallback to default language.

## Translation Files

Located in `app/lang/{lang}.php`. Return associative array:

```php
// app/lang/en.php
return [
    'welcome'    => 'Welcome',
    'hello_user' => 'Hello, :name!',
];
```

Keys must be valid variable names (`[a-zA-Z_][a-zA-Z0-9_]*`).

## Usage

```php
// Simple string
echo i18n('welcome');                           // "Welcome"

// With placeholders
echo i18n()->get('hello_user', ['name' => 'Alex']); // "Hello, Alex!"

// Missing key returns ::key::
echo i18n('nonexistent');                       // "::nonexistent::"
```

## Placeholders

Format `:name` in translation string. Replaced from array:

```php
// en.php: 'greeting' => 'Hello, :name! Balance: :balance'

i18n()->get('greeting', ['name' => 'Alex', 'balance' => 1500]);
// "Hello, Alex! Balance: 1500"
```

## Language Selection

Priority order:
1. Cookie `lang` (user-set)
2. `config.ini`: `lang = ru`
3. Default: `en`

```php
i18n()->setCurrentLang('ru');     // switch (saves to cookie)
i18n()->getCurrentLang();         // "ru"
i18n()->getDefaultLang();         // "en"
i18n()->getLanguages();           // ['en' => 'English', 'ru' => 'Русский', ...]
```

## Supported Languages

Predefined: `en`, `ru`, `ua`, `be`, `de`, `fr`. Only those with existing files in `app/lang/` are actually available.

To add a new language: create `app/lang/es.php` and add the code to the `$languages` array inside the class.

## Fallback

If key missing in current language → checked in default (`en`). If still missing → returns `::key::`.

## Notes

- Translation files are cached (loaded once)
- `strtr` used for placeholder replacement (faster than `str_replace`)
- Key names are validated (`isVariableName`)
- Current language saved in cookie `lang`

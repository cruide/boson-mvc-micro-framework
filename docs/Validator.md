# Validator Documentation (Boson Framework)

**Version:** 2.1

Pipe-syntax validation with 25+ built-in rules. Supports custom validators, i18n messages, and reusable instances.

## Quick Start

```php
$validator = validator($data, [
    'email' => 'required|email|maxlen:255',
    'age'   => 'required|int|min:18|max:99',
]);

if ($validator->fails()) {
    return json_response(['errors' => $validator->errors()], 422);
}

$clean = $validator->validated();
```

## Initialization

```php
// Global function
$validator = validator($values, $rules);

// Constructor
$validator = new Boson\Validator($values, $rules);

// Static factory
$validator = Boson\Validator::make($values, $rules);
```

## Rule Formats

```php
// Pipe-delimited string
'username' => 'required|alpha|minlen:3|maxlen:20'

// Array format
'username' => ['required', 'alpha', 'minlen' => 3, 'maxlen' => 20]

// Closure
'username' => function($field, $value, $allValues) {
    return $value !== 'admin'; // true — ok, false/string — error
}
```

## Available Rules

| Rule | Description | Example |
|---|---|---|
| `required` | Field must be filled | `required` |
| `nullable` | Field may be null (but validated if present) | `nullable` |
| `trim` | Trim whitespace before validation | `trim` |
| `int` / `integer` | Integer | `int` |
| `float` | Float | `float` |
| `bool` / `boolean` | Boolean | `bool` |
| `numeric` | Number (int or float) | `numeric` |
| `email` | Email address | `email` |
| `url` | URL | `url` |
| `json` | Valid JSON string | `json` |
| `alpha` | Letters only (incl. Cyrillic) | `alpha` |
| `alphanum` | Letters and digits | `alphanum` |
| `date` | Valid date | `date`, `date:Y-m-d` |
| `regexp` | Regular expression | `regexp:/^\d+$/` |
| `minlen` | Min string length | `minlen:5` |
| `maxlen` | Max string length | `maxlen:50` |
| `min` | Min numeric value | `min:10` |
| `max` | Max numeric value | `max:100` |
| `same` | Must match another field | `same:password_confirm` |
| `confirmed` | Must match `{field}_confirmation` | `confirmed` |
| `in` | Value in list | `in:active,banned` |
| `not_in` | Value not in list | `not_in:admin,root` |
| `validator` | Call `is_{name}()` function | `validator:phone` |

## Custom Rules

```php
$validator->addRule('even', function($value, $params, $allValues) {
    return $value % 2 === 0;
});
// Usage: 'number' => 'required|even'
```

Callback signature: `function(mixed $value, array $params, array $allValues): bool|string`
- `true` — passes
- `false` — fails with default message
- `string` — fails with custom message

## Custom Messages

```php
$validator->setMessages([
    'email.required' => 'Email is required.',
    'email.email'    => 'Invalid email format.',
    'age.min'        => 'Age must be at least 18.',
]);
```

Or inline in rules:
```php
'email' => 'required|email|message:Please enter a valid email'
```

## Error Handling

```php
$validator->fails();      // true if errors exist
$validator->passes();     // true if no errors

$errors = $validator->errors();       // short alias
$errors = $validator->getMessages();  // full name
$errors = $validator->getErrors();    // another alias
// Returns: ['email' => ['Error 1', 'Error 2']]

$msg = $validator->first();           // first error overall
$msg = $validator->first('email');    // first error for field

$validator->hasError('email');        // true/false
$fields = $validator->failed();       // ['email', 'age']
```

## Validated Data

```php
$data = $validator->validated();      // fields with rules (regardless of errors)
$data = $validator->only(['email']);  // specific fields only
$data = $validator->validateOrFail(); // throws RuntimeException on failure
```

## Reusability

```php
$validator = new Validator();
$validator->setValues($newData);
$validator->setRules($newRules);
$validator->fails();
```

## i18n

If `i18n()` function exists, error messages use translation keys prefixed with `validator_` (e.g., `validator_required`, `validator_email`). String length uses `Str::length()` (UTF-8 aware).

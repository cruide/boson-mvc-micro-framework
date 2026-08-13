# Документация класса Validator (Boson Framework)

**Версия:** 2.1

Класс `Boson\Validator` — гибкий валидатор входных данных. Поддерживает строковые правила (pipe-синтаксис), массивы, Closure-валидаторы, кастомные правила, локализацию сообщений.

---

## Быстрый старт

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

---

## Инициализация

```php
// Через глобальную функцию
$validator = validator($values, $rules);

// Через конструктор
$validator = new Boson\Validator($values, $rules);

// Статический фабричный метод
$validator = Boson\Validator::make($values, $rules);
```

---

## Правила валидации

Правила задаются строкой с разделителем `|`, массивом или Closure.

### Строковый формат
```php
'username' => 'required|alpha|minlen:3|maxlen:20'
```

### Массивный формат
```php
'username' => ['required', 'alpha', 'minlen' => 3, 'maxlen' => 20]
```

### Closure
```php
'username' => function($field, $value, $allValues) {
    return $value !== 'admin'; // true — ок, false/string — ошибка
}
```

### Список правил

| Правило | Описание | Пример |
| :--- | :--- | :--- |
| **Обязательность** | | |
| `required` | Поле должно быть заполнено | `required` |
| `nullable` | Поле может быть null (но если передано — валидируется) | `nullable` |
| **Типы** | | |
| `int` / `integer` | Целое число | `int` |
| `float` | Число с плавающей точкой | `float` |
| `bool` / `boolean` | Булево значение | `bool` |
| `numeric` | Число (int или float) | `numeric` |
| `json` | Валидная JSON-строка | `json` |
| **Строки и форматы** | | |
| `email` | Email-адрес | `email` |
| `url` | URL | `url` |
| `alpha` | Только буквы (включая кириллицу) | `alpha` |
| `alphanum` | Буквы и цифры | `alphanum` |
| `date` | Валидная дата | `date`, `date:Y-m-d` |
| `regexp` | Регулярное выражение | `regexp:/^\d+$/` |
| **Длина и размер** | | |
| `minlen` | Минимальная длина строки | `minlen:5` |
| `maxlen` | Максимальная длина строки | `maxlen:50` |
| `min` | Минимальное числовое значение | `min:10` |
| `max` | Максимальное числовое значение | `max:100` |
| **Сравнение** | | |
| `same` | Совпадает с другим полем | `same:password_confirm` |
| `confirmed` | Совпадает с полем `{field}_confirmation` | `confirmed` |
| `in` | Значение в списке | `in:active,banned` |
| `not_in` | Значение не в списке | `not_in:admin,root` |
| **Системные** | | |
| `validator` | Вызов функции `is_{name}()` | `validator:phone` |
| `trim` | Обрезать пробелы перед проверкой | `trim` |

---

## Кастомные правила

```php
$validator = validator($data, $rules);

$validator->addRule('even', function($value, $params, $allValues) {
    return $value % 2 === 0;
});

// Использование: 'number' => 'required|even'
```

Сигнатура callback: `function(mixed $value, array $params, array $allValues): bool|string`
- `true` — ок
- `false` — ошибка со стандартным сообщением
- `string` — ошибка с указанным текстом

---

## Пользовательские сообщения

```php
$validator->setMessages([
    'email.required' => 'Пожалуйста, укажите email.',
    'email.email'    => 'Некорректный формат email.',
    'age.min'        => 'Возраст должен быть не менее 18 лет.',
]);
```

Также можно указать сообщение прямо в строке правил:
```php
'email' => 'required|email|message:Укажите корректный email'
```

---

## Получение ошибок

```php
// Проверка
$validator->fails();   // true — есть ошибки
$validator->passes();  // true — ошибок нет

// Все ошибки: [поле => [сообщение1, сообщение2]]
$errors = $validator->errors();     // короткий алиас
$errors = $validator->getMessages(); // полное имя
$errors = $validator->getErrors();   // ещё один алиас

// Первая ошибка
$msg = $validator->first();          // первая ошибка вообще
$msg = $validator->first('email');   // первая ошибка поля email

// Проверка поля
$validator->hasError('email');  // true/false

// Список полей с ошибками
$fields = $validator->failed();  // ['email', 'age']
```

---

## Валидированные данные

```php
// Все поля с правилами (независимо от ошибок — проверьте fails() сначала!)
$data = $validator->validated();

// Только указанные поля
$data = $validator->only(['email', 'name']);

// Данные или исключение при ошибках
$data = $validator->validateOrFail(); // выбрасывает RuntimeException
```

---

## Повторное использование

```php
$validator = new Validator();

// Меняем данные
$validator->setValues($newData);
// Меняем правила
$validator->setRules($newRules);

$validator->fails();
```

---

## Справочник методов

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `__construct($values, $rules)` | Конструктор | `void` |
| `make($values, $rules)` | Статический фабричный метод | `Validator` |
| `setValues($values)` | Заменить данные | `self` |
| `setRules($rules)` | Заменить правила | `self` |
| `setMessages($messages)` | Установить сообщения об ошибках | `self` |
| `addRule($name, $callback)` | Зарегистрировать кастомное правило | `self` |
| `checkAll()` | Проверить все поля | `bool` |
| `check($field)` | Проверить одно поле | `bool` |
| `fails()` | Есть ли ошибки | `bool` |
| `passes()` | Нет ошибок | `bool` |
| `errors()` | Все ошибки | `array` |
| `getMessages()` | Все ошибки (полное имя) | `array` |
| `getErrors()` | Все ошибки (алиас) | `array` |
| `first($field?)` | Первая ошибка | `?string` |
| `getFirstMessage($field)` | Первая ошибка поля | `?string` |
| `hasError($field)` | Есть ли ошибка у поля | `bool` |
| `failed()` | Список полей с ошибками | `array` |
| `validated()` | Валидированные данные | `array` |
| `only($keys)` | Часть валидированных данных | `array` |
| `validateOrFail()` | Данные или исключение | `array` |

---

## Примечания

1. **i18n:** Если функция `i18n()` существует, сообщения об ошибках берутся через неё. Ключи локализации имеют префикс `validator_` (например, `validator_required`, `validator_email`).
2. **Кодировка:** Для подсчёта длины строк используется `Str::length()` (UTF-8).
3. **Остановка на ошибке:** По умолчанию проверка поля останавливается после первой ошибки. Для сбора всех ошибок передайте `false` третьим аргументом конструктора.
4. **`trim`:** Если указано правило `trim`, значение обрезается до проверки последующих правил и сохраняется обрезанным в `$this->values`. `validated()` вернёт уже обрезанное значение.

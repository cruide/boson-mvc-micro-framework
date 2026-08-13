# Документация класса `Boson\Input` (Boson Framework)

**Версия:** 2.1

Класс `Input` предназначен для безопасной и унифицированной работы с входными данными HTTP-запроса. Он объединяет доступ к параметрам `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, JSON-телу, заголовкам, файлам и cookie.

**Основные возможности:**
*   **Безопасность:** Автоматическая XSS-очистка данных при получении.
*   **Универсальность:** Единый интерфейс для всех типов данных запроса.
*   **Производительность:** Кэширование заголовков, метода, тела запроса и XSS-очистки.
*   **Типизированный доступ:** `int()`, `float()`, `bool()`, `string()`, `date()`, `array()`.
*   **Валидация:** Проверка ключей на допустимые символы.

---

## 1. Инициализация

Класс реализует паттерн **Singleton**. Доступ через глобальную функцию `input()`:

```php
// Получить экземпляр
$input = input();

// Получить значение параметра
$username = input('username');
$limit    = input('limit', 10);
```

---

## 2. Работа с параметрами запроса

Данные из `$_GET`, `$_POST` и тела запроса (для PUT/PATCH/DELETE) автоматически собираются в конструкторе.

### Получение значения
Метод `input()` возвращает XSS-очищенное значение параметра.

```php
$username = input('username');
$limit    = input('limit', 10);
```

### Получение всех данных
```php
$data = input()->all();
```

### Проверка наличия данных
```php
if( input()->filled('email') ) {
    // Ключ существует И значение не пустое
}

if( input()->missing('token') ) {
    // Ключ отсутствует ИЛИ значение пустое
}

if( input()->has('id') ) {
    // Ключ существует (даже если значение null)
}
```

### Фильтрация данных
```php
$userData = input()->only(['name', 'email', 'age']);
$settings = input()->except(['password', 'password_confirm']);
```

---

## 3. Типизированный доступ

Методы для безопасного приведения типов. Не применяют XSS-очистку (кроме `string()`), работают быстрее чем `input()`.

### Числа
```php
$id    = input()->int('id', 0);       // (int)
$price = input()->float('price', 0.0); // (float)
```

### Булево
Распознаёт строки `'1'`, `'true'`, `'yes'`, `'on'` как `true`, и `'0'`, `'false'`, `'no'`, `'off'`, `''` как `false`.

```php
$active   = input()->bool('active');        // из чекбокса
$remember = input()->bool('remember', false);
```

### Строка
```php
$name = input()->string('name', ''); // с XSS-очисткой
```

### Массив
```php
$tags  = input()->array('tags', []);
$roles = input()->array('roles');
```

### Дата
```php
$birthday = input()->date('birthday');                // DateTime или null
$from     = input()->date('from', 'Y-m-d');           // с указанием формата
```

---

## 4. GET и POST раздельно

Когда нужно получить значение строго из одного источника:

```php
// Только GET-параметры (строка запроса)
$page  = input()->query('page', 1);
$sort  = input()->query('sort', 'name');
$allGet = input()->query(); // весь массив GET

// Только POST-параметры (тело формы)
$email  = input()->post('email');
$allPost = input()->post(); // весь массив POST
```

В отличие от `input()` (который ищет во всех источниках), `query()` и `post()` читают строго из `$_GET` и `$_POST` соответственно. XSS-очистка применяется.

---

## 5. HTTP Метод и Тип запроса

```php
$method = input()->method();

if( input()->isPost() ) { /* обработка формы */ }
if( input()->isMethod('PUT') ) { /* API обновление */ }
```

*Поддерживается переопределение метода через заголовок `X-HTTP-Method-Override` или поле `_method`.*

### AJAX и JSON-проверки
```php
// XMLHttpRequest?
if( input()->isAjax() ) { ... }

// Content-Type: application/json?
if( input()->isJson() ) {
    $data = input()->json();
}

// Клиент ожидает JSON-ответ? (AJAX или Accept: application/json)
if( input()->expectsJson() ) {
    return json_response($data);
}
```

### Тип соединения
```php
if( input()->isSecure() ) {
    // HTTPS
}
```

---

## 6. Заголовки (Headers)

```php
$contentType = input()->header('Content-Type');
$authHeader  = input()->header('Authorization');
$allHeaders  = input()->headers();
```

### Bearer Токен
```php
$token = input()->bearerToken();
if( $token === false ) {
    // Токен не найден
}
```

---

## 7. JSON и Raw Input

```php
// Весь JSON payload
$data = input()->json();

// Конкретное поле
$userId = input()->json('user_id', 0);

// Сырое тело запроса
$raw = input()->raw();
```

---

## 8. Файлы

```php
if( input()->hasFile('avatar') ) {
    $fileInfo = input()->file('avatar');
    move_uploaded_file($fileInfo['tmp_name'], '/path/to/save');
}

$files = input()->files();
```

---

## 9. Окружение, Cookies и Клиент

```php
$sessionId    = input()->cookie('session_id', 'default');
$documentRoot = input()->server('DOCUMENT_ROOT');
$ip           = input()->ip();        // учитывает прокси
$ua           = input()->userAgent();
$uri          = input()->uri();       // полный URI с query string
$path         = input()->path();      // путь без query string
```

---

## 10. Безопасность и Валидация

### XSS Защита
Все строковые значения, получаемые через `input()`, `string()`, `query()`, `post()`, автоматически проходят очистку от XSS-атак. Результат кэшируется — повторные запросы того же ключа не фильтруются заново.

Методы `int()`, `float()`, `bool()`, `array()`, `date()` XSS-очистку **не** применяют — для чисел и булевых значений она не нужна.

### Валидация ключей
По умолчанию ключи с недопустимыми символами игнорируются. Допустимые символы: `a-z`, `0-9`, `_`, `-`, `/`, `:`, `.`.

Строгий режим (прерывает запрос при невалидном ключе):

```php
// Программно:
input()->setStrictKeyValidation(true);

// Или через конфиг app/configs/config.ini:
// input_strict_key_validation = on
```

---

## Справочник методов

### Базовый доступ

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `input($name, $default)` | Параметр с XSS-очисткой | `mixed` |
| `all()` | Все параметры запроса | `array` |
| `filled($name)` | Параметр есть и не пуст | `bool` |
| `missing($name)` | Параметра нет или он пуст | `bool` |
| `has($name)` | Ключ существует (даже если null) | `bool` |
| `only($keys)` | Массив только с указанными ключами | `array` |
| `except($keys)` | Массив всех ключей кроме указанных | `array` |

### Типизированный доступ

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `int($name, $default)` | Целое число | `int` |
| `float($name, $default)` | Число с плавающей точкой | `float` |
| `bool($name, $default)` | Булево ('1','true','yes','on' → true) | `bool` |
| `string($name, $default)` | Строка с XSS-очисткой | `string` |
| `array($name, $default)` | Массив | `array` |
| `date($name, $format?, $default?)` | Объект DateTime | `?\DateTime` |

### Источники

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `query($key?, $default?)` | Только GET-параметры | `mixed` |
| `post($key?, $default?)` | Только POST-параметры | `mixed` |
| `json($key?, $default?)` | Данные из JSON-тела | `mixed` |
| `raw()` | Сырое тело запроса | `string` |

### HTTP

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `method()` | HTTP метод | `string` |
| `isGet()`, `isPost()`, `isPut()`, `isPatch()`, `isDelete()` | Проверка метода | `bool` |
| `isMethod($name)` | Проверка произвольного метода | `bool` |
| `isAjax()` | X-Requested-With: XMLHttpRequest | `bool` |
| `isJson()` | Content-Type содержит application/json | `bool` |
| `expectsJson()` | AJAX или Accept: /json | `bool` |
| `isSecure()` | HTTPS | `bool` |

### Заголовки

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `header($name, $default?)` | HTTP заголовок | `mixed` |
| `headers()` | Все заголовки | `array` |
| `bearerToken()` | Bearer токен из Authorization | `string\|false` |

### Файлы / Cookies / Клиент

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `file($name)` | Данные файла | `?array` |
| `hasFile($name)` | Файл загружен успешно | `bool` |
| `files()` | Все файлы | `array` |
| `cookie($name, $default?)` | Значение Cookie | `mixed` |
| `server($name, $default?)` | Переменная $_SERVER | `mixed` |
| `ip()` | IP адрес клиента | `string` |
| `userAgent()` | User-Agent | `?string` |
| `uri()` | Полный URI | `string` |
| `path()` | Путь без query string | `string` |

### Настройки

| Метод | Описание | Возвращает |
| :--- | :--- | :--- |
| `setStrictKeyValidation($bool)` | Строгая валидация ключей | `self` |

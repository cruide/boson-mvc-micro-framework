# Документация — глобальные функции (Boson Functions)

Файл `boson/Functions.php` содержит вспомогательные функции фреймворка, доступные глобально.

---

## Работа с путями и файлами

### `path_correct($dir, $end_slash?)`
Нормализация пути: заменяет все разделители на `DIRECTORY_SEPARATOR`, опционально добавляет слеш в конце.

### `get_filename($path_string)`
Имя файла из полного пути.

### `get_file_extension($filename)`
Расширение файла (без точки).

### `generate_file_name($extension)`
Генерация случайного имени файла: `unix_time + random_hash + extension`.

### `file_put_gz_content($filename, $content, $level?)`
Запись в файл с gzip-сжатием.

### `file_get_gz_content($filename)`
Чтение gzip-файла с распаковкой.

### `gz_file_pack($file_in, $file_out?)`
Упаковка существующего файла в `.gz`.

### `is_image($filepath)`
Проверка: является ли файл изображением (через `getimagesize()`).

### `get_image_extension($filepath)`
Определение расширения изображения по MIME-типу (jpg, png, gif, webp, svg...).

---

## Работа с массивами

### `array_count($_, $mode?)`
Безопасный `count()`: возвращает 0 для не-массивов.

### `array_key_isset($key, $_array)`
Проверка существования ключа (даже если значение `null`). Аналог `isset() || array_key_exists()`.

### `array_get_first($_array, $key?)`
Первый элемент массива (или `[key => value]` если `$key=true`).

### `array_get_first_key($_array)`
Ключ первого элемента.

### `array_get_last($_array, $key?)`
Последний элемент массива.

### `array_get_last_key($_array)`
Ключ последнего элемента.

### `trim_array($items)`
Рекурсивный `trim()` для всех строковых значений в массиве.

### `obj_to_array($obj)`
Рекурсивное преобразование объекта в массив (только публичные свойства).

---

## Валидация и проверки

### `is_email($str)`
Валидация email через `filter_var(FILTER_VALIDATE_EMAIL)`.

### `is_url($str)`
Валидация URL через `filter_var(FILTER_VALIDATE_URL)`.

### `is_alphanum($str)`
Только буквы (лат/кир) и цифры.

### `is_alpha($str)`
Только буквы (лат/кир).

### `is_date($str)`
Проверка формата `DD.MM.YYYY`.

### `is_name($str)`
Буквы, цифры, пробелы, апостроф (для имён).

### `is_variable_name($str)`
Валидное имя переменной PHP: `[a-z0-9_]+`, не начинается с цифры.

### `is_action_name($str)`
Валидное имя экшена: `[a-z0-9_\-]+`, не начинается с цифры/подчёркивания/дефиса.

### `is_uuid($uuid)`
Валидация UUID v4.

### `is_ipaddress($str)`
Валидация IP-адреса.

### `is_url_exists($url)`
Проверка: отвечает ли URL (через `get_headers()`).

### `is_ajax()`
Проверка AJAX-запроса (заголовки `X-Requested-With`, `Accept`).

---

## Строки и регистр

### `escape($str, $method?)`
Экранирование: `htmlspecialchars` или `htmlentities` (рекурсивно для массивов).

### `unescape($str)`
Обратное экранирование: `htmlspecialchars_decode` (рекурсивно).

### `studly_case($value)`
`hello_world` → `HelloWorld`. С кэшированием.

### `camel_case($value)`
`hello_world` → `helloWorld`.

### `snake_case($value, $delimiter?, $convert_spaces?)`
`HelloWorld` → `hello_world`. С кэшированием.

### `str_ucfirst($str)`
Алиас `Str::ucfirst()` — заглавная первая буква (multibyte).

### `str_lower($str)`
Алиас `Str::lower()` — нижний регистр (multibyte).

### `str_upper($str)`
Алиас `Str::upper()` — верхний регистр (multibyte).

### `str_length($str)`
Алиас `Str::length()` — длина строки (multibyte).

---

## Безопасность и криптография

### `password_crypt($password)`
Хеширование пароля через `password_hash(PASSWORD_BCRYPT)`.

### `password_verify_legacy($password, $storedHash)`
Проверка старого хеша (MD5 + crypt). Для обратной совместимости.

### `password_generate($number?)`
Генерация случайного пароля из букв и цифр.

### `encrypt($string, $key?)`
AES-256-GCM шифрование. Ключ по умолчанию: `boson_encryption_key()` (env `BOSON_APP_KEY` → конфиг `encryption_key` → legacy-фоллбэк).

### `decrypt($cipher, $key?)`
Дешифровка: AES-256-GCM (v2) + RC4 fallback для старых данных.

### `str_base64_encrypt($str, $key?, $gz?)`
Шифрование + base64 (+ опционально gzip).

### `str_base64_decrypt($str, $key?, $gz?)`
Обратная операция.

### `salt_generation($length?)`
Генерация соли: `sha1(random_bytes(32))`.

### `uuid()`
Генерация UUID v4.

---

## HTTP и ответы

### `redirect($data?, $status?)`
HTTP-редирект. Поддерживает: строку URL, массив `['controller'=>..., 'message'=>...]`, или на главную.

### `abort($message, $code?)`
Завершение с HTTP-статусом ошибки и шаблоном `errors/{code}`.

### `abort_json($data, $code?)`
JSON-ответ с ошибкой и HTTP-статусом.

### `json_response($data, $status?)`
JSON-ответ (отключает layout, ставит Content-Type).

### `http_cache_off()`
Заголовки для отключения кеширования.

### `send_header_app_info()`
Заголовки `X-Boson-Time` и `X-Boson-Memory`.

### `cors()`
CORS-заголовки. Использует `config.ini:cors_origins` для дополнительных доменов.

### `execution_timeout($seconds?)`
Установка `max_execution_time` и `max_input_time`.

---

## Дата и время

### `boson_date_format($string, $format?, $default?, $formatter?)`
Форматирование даты с поддержкой `strftime`-форматов. Принимает SQL-дату, timestamp, DateTime.

### `boson_make_timestamp($string)`
Преобразование строки/DateTime/SQL-даты в Unix-timestamp.

### `carbon($datetime?, $format?)`
Хелпер для Carbon: `carbon()` — сейчас, `carbon('2024-01-01', 'd.m.Y')` — форматирование.

### `date_week_name($stamp, $full?)`
Название дня недели (русский): `'пн'` или `'понедельник'`.

### `get_month_by_num($num)`
Название месяца по номеру (русский).

### `make_path_from_date($utime?)`
Путь вида `YYYY/MM/DD` из timestamp.

---

## Числа и вычисления

### `float_extract($num)`
Разделение float на целую и дробную части.

### `percentage($quantity, $value)`
Процент `$value` от `$quantity`.

### `num2word($num, $words)`
Склонение числительных: `num2word(5, ['литр','литра','литров'])` → `'литров'`.

### `rating_stars($rating)`
Звёзды рейтинга: `rating_stars(4)` → `'★★★★'`.

### `in_radius($px, $py, $cx, $cy, $cr)`
Проверка: входит ли точка в круг.

---

## Прочее

### `cfg($name, $keyname?, $default?)`
Чтение `.ini` конфигурации (кэшируется). Возвращает `BosonObject` или конкретный ключ.

### `view($phtml?, $variables?)`
Рендер шаблона текущим движком. Без аргументов возвращает экземпляр движка.

### `theme($name?)`
Экземпляр `Boson\Theme`. С аргументом — смена темы.

### `get_ip_address()`
IP-адрес клиента (с учётом прокси: `HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`).

### `get_http_status($code)`
Текст HTTP-статуса по коду: `get_http_status(404)` → `'404 Not Found'`.

### `controller_exists($action)`
Проверка существования файла контроллера.

### `make_controller($controller_name, $before?)`
Создание экземпляра контроллера (с опциональным вызовом `_before()`).

### `make_url($url, $add_suf?)`
Построение абсолютного URL из относительного.

### `memory_clear()`
Принудительная сборка мусора `gc_collect_cycles()`.

### `get_mem_use($max?, $round_to?)`
Использование памяти в МБ (пиковое или текущее).

### `get_object_public_vars($obj)`
Публичные свойства объекта.

### `javascript($str)`
Оборачивание в `<script>` тег (для JSON-редиректов).

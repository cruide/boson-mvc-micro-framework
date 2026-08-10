# Документация класса Boson\Native (Boson Framework)

**Версия:** 2.1

Класс `Native` — легкий шаблонизатор для PHP-шаблонов (`.phtml`). API частично совместим со Smarty для упрощения миграции.

## Инициализация

```php
$tpl = new Native('/path/to/templates/');
```

Конструктор принимает путь к директории с шаблонами. Если директория не существует — `NativeException`.

## Переменные шаблона

### Локальные (`assign`)

```php
$tpl->assign('title', 'Главная');        // одно значение
$tpl->assign(['user' => 'Alex', 'role' => 'admin']); // массово
$tpl->assign('key', null);               // удалить
```

### Глобальные (`setGlobal`)

Статические — доступны во всех экземплярах класса.

```php
Native::setGlobal('site_name', 'My Site');
Native::setGlobal(['version' => '1.0']);
Native::setGlobal('old', null);  // удалить
```

Нестатический алиас: `$tpl->assignGlobal('key', 'value')` (совместимость со Smarty).

## Рендеринг

### `fetch($file_name, $need_ext?, $xhtml?)`

Рендерит шаблон и возвращает строку.

| Параметр | По умолчанию | Описание |
|---|---|---|
| `$file_name` | — | Имя файла |
| `$need_ext` | `true` | Авто-добавление `.phtml` |
| `$xhtml` | `XHTML_CRRECTION_OFF` | XHTML-коррекция |

```php
$html = $tpl->fetch('index');        // → index.phtml
$html = $tpl->fetch('custom.tpl', false); // без авто-расширения
```

### `display($file_name, $xhtml?)`

Рендерит и сразу выводит (`echo`). Отправляет `X-Boson-*` заголовки.

## Управление переменными

### `remove($name)`

Удалить переменную из экземпляра.

### `flushProperties()`

Очистить все локальные переменные экземпляра.

## XHTML-коррекция

Исправляет: атрибуты без кавычек, незакрытые теги `<br>`, `<img>`, `<hr>`, лишние пробелы.

```php
// Для конкретного вызова:
$tpl->fetch('page', true, Native::XHTML_CRRECTION_ON);

// Глобально:
Native::$correctXHTML = true;
```

## Константы

| Константа | Значение |
|---|---|
| `EXTENSION` | `'phtml'` |
| `XHTML_CRRECTION_ON` | `true` |
| `XHTML_CRRECTION_OFF` | `false` |

## Безопасность

- `extract()` использует флаг `EXTR_SKIP` — переменные шаблона не могут переопределить существующие переменные (включая суперглобалы).
- Не передавайте пользовательский ввод в имя файла шаблона без проверки.

## Зависимости

- `path_correct()` — нормализация путей
- `send_header_app_info()` — для метода `display()`

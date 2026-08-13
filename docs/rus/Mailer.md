# Документация к классу `Mailer` (Boson Framework)

**Версия:** 2.1

Обёртка над PHPMailer с поддержкой шаблонов Smarty и Native (PHTML). Fluent API, авто-конфигурация из `mailer.ini`.

## Зависимости

- `phpmailer/phpmailer` — отправка писем
- `smarty/smarty` — опционально, для Smarty-шаблонов
- `cfg()` — чтение конфигурации `mailer.ini`

## Конфигурация (`mailer.ini`)

```ini
email_from = "admin@example.com"
from_name  = "Admin"
type       = "smtp"
host       = "smtp.example.com"
port       = 587
username   = "user@example.com"
password   = "password"
authtype   = "LOGIN"
replyto    = "support@example.com"
```

Без `email_from` конструктор выбросит исключение.

**Типы транспорта:** `smtp`, `sendmail`, `qmail`, `mail` (по умолчанию).

## Методы

### Конструктор
```php
$mailer = new Mailer();
```
Инициализирует директории, загружает настройки, создаёт PHPMailer и шаблонизатор.

### Отправка

#### `send(string $template, ?array $values = null): bool`
Рендерит шаблон и отправляет письмо.
```php
$mailer->to('user@site.ru')
       ->subject('Привет')
       ->send('welcome', ['name' => 'Иван']);
// Использует шаблон app/mails/welcome.tpl (или .phtml для Native)
```

#### `sendHTML(string $html): bool`
Отправляет готовый HTML без шаблона.
```php
$mailer->to('user@site.ru')->sendHTML('<h1>Привет!</h1>');
```

### Настройка письма (fluent interface)

| Метод | Описание |
|---|---|
| `from($address, $name?, $auto?)` | Адрес отправителя |
| `to($address, $name?)` | Получатель |
| `cc($address, $name?)` | Копия |
| `bcc($address, $name?)` | Скрытая копия |
| `subject($subject)` | Тема (с экранированием HTML) |
| `attach($path, $name?, $encoding?, $type?, $disposition?)` | Вложение |
| `assign($tpl_var, $value?)` | Переменная шаблона |
| `clear()` | Очистить получателей и вложения |
| `reset()` | Полный сброс (получатели, вложения, тема, тело, заголовки) + переприменить from |

### Шаблоны

```php
$mailer->fetch('template'); // вернуть HTML строку
```

Расширение добавляется автоматически: `.tpl` для Smarty, `.phtml` для Native. Движок выбирается по `cover` в `config.ini`.

### Доступ к внутренним объектам

```php
$mailer->getMailer();          // PHPMailer — для тонкой настройки
$mailer->getTemplateEngine();  // Smarty или Native
```

### Магический `__call`

Любой метод PHPMailer можно вызвать напрямую:
```php
$mailer->addCustomHeader('X-Priority', '1');
$mailer->isHTML(true);
```

## Примеры

### Простая отправка
```php
$mailer = new Mailer();
$mailer->from('admin@site.ru', 'Администратор')
       ->to('user@site.ru')
       ->subject('Добро пожаловать')
       ->send('welcome', ['name' => 'Иван']);
```

### Сброс для повторной отправки
```php
$mailer->to('first@site.ru')->subject('Первое')->send('template');
$mailer->reset()
       ->to('second@site.ru')->subject('Второе')->send('template');
```

### Шаблон (`app/mails/welcome.tpl`)
```html
<h1>Здравствуйте, {$name}!</h1>
<p>Добро пожаловать на сайт.</p>
```

## Ошибки

Исключения PHPMailer логируются через `error_log()` и пробрасываются как `Exception`.

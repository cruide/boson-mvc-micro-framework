# Mailer Class Documentation (Boson Framework)

**Version:** 2.1

PHPMailer wrapper with Smarty/Native template support. Fluent API. Auto-configures from `mailer.ini`.

## Configuration (`mailer.ini`)

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

`email_from` is required. Transport types: `smtp`, `sendmail`, `qmail`, `mail` (default).

## Sending

```php
$mailer = new Mailer();

// With template:
$mailer->to('user@site.com')
       ->subject('Welcome')
       ->send('welcome', ['name' => 'Ivan']);
// Uses app/mails/welcome.tpl (or .phtml for Native)

// Raw HTML:
$mailer->to('user@site.com')->sendHTML('<h1>Hello!</h1>');
```

## Fluent Methods

| Method | Description |
|---|---|
| `from($addr, $name?, $auto?)` | Sender |
| `to($addr, $name?)` | Recipient |
| `cc($addr, $name?)` | Carbon copy |
| `bcc($addr, $name?)` | Blind carbon copy |
| `subject($text)` | Subject (HTML-escaped) |
| `attach($path, $name?, ...)` | Attachment |
| `assign($var, $value?)` | Template variable |
| `clear()` | Clear recipients and attachments |
| `reset()` | Full reset (recipients, attachments, subject, body, headers) + re-apply from |

## Templates

```php
$html = $mailer->fetch('template'); // render to string
```

Extension auto-added: `.tpl` for Smarty, `.phtml` for Native. Engine chosen by `cover` in `config.ini`.

## Internal Access

```php
$mailer->getMailer();          // PHPMailer instance
$mailer->getTemplateEngine();  // Smarty or Native
```

## Magic `__call`

Any PHPMailer method can be called directly:
```php
$mailer->addCustomHeader('X-Priority', '1');
$mailer->isHTML(true);
```

## Reuse

```php
$mailer->to('first@site.com')->subject('First')->send('tpl');
$mailer->reset()
       ->to('second@site.com')->subject('Second')->send('tpl');
```

## Errors

PHPMailer exceptions are logged via `error_log()` and re-thrown as `Exception`.

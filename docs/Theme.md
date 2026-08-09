# Документация класса `Theme` (Boson Framework)

**Версия:** 2.1

Управление темами и рендерингом. Поддерживает горячую смену движка (Smarty / Native PHTML) и темы оформления. Единый экземпляр движка для layout и view.

## Конфигурация (`config.ini`)

```ini
theme  = "smarty"                               ; Папка темы в themes/
layout = "layout"                               ; Файл макета
cover  = "smarty"                               ; Движок: smarty или native (PHTML)

; Защитные заголовки (0 = отключить)
x_frame_options        = "DENY"
x_content_type_options = "nosniff"
referrer_policy        = "strict-origin-when-cross-origin"
```

## Горячая смена движка

Достаточно изменить `cover` в `config.ini`:
- `smarty` — шаблоны `.tpl` с синтаксисом `{$var}`, `{if}`, `{i18n}`
- `native` — шаблоны `.phtml` с нативным PHP `<?=$var?>`, `<?php if(): ?>`

Контроллеры не требуют изменений — `view('path')` работает одинаково.

## Горячая смена темы

```php
theme('newtheme'); // переключает папку шаблонов на лету
```

## Назначение переменных

```php
// В контроллере
theme()->assign('title', 'Моя страница');
theme()->assign('users', $users);
```

Глобальные переменные (доступны всегда): `{$base_url}`, `{$js_url}`, `{$css_url}`, `{$images_url}`, `{$content_url}`.

## Динамический CSS/JS

```php
// В контроллере — добавить стиль/скрипт на лету:
theme()->useThemeCss('extra.css');
theme()->useThemeJs('widget.js', $head = false);  // false = перед </body>
theme()->useExternalJs('https://cdn.example.com/lib.js');
```

В шаблоне доступны переменные:
- `{$boson_css}` — массив URL стилей
- `{$boson_js_head}` — массив URL скриптов для `<head>`
- `{$boson_js_body}` — массив URL скриптов для `<body>`

Если шаблон их не использует — работает авто-инжекция через regex (совместимость).

## Рендеринг

```php
// В контроллере:
return view('index/index');              // themes/{theme}/views/index/index.tpl
return view('index/index', ['var' => 1]); // с переменными

// JSON без layout:
return json_response(['status' => 'ok']);

// Без layout вручную:
theme()->disableLayout();
echo $content;
```

## Плагины Smarty

Стандартные: `{i18n str="ключ"}`, `{num2word number=n words=['год','года','лет']}`.

Регистрация своих:
```php
theme()->addPlugin('function', 'myplugin', 'smarty_function_myplugin');
```

## Flash-сообщения

```php
session()->flash('message', 'Сохранено!');
// На следующем запросе: $message = session()->flash('message');
// Автоматически удаляется после чтения
```

## Защитные заголовки

Отправляются автоматически. Настраиваются в `config.ini`. Значение `0` отключает конкретный заголовок.

## Методы

| Метод | Описание |
|---|---|
| `assign($name, $value)` | Переменная для шаблона |
| `setGlobals()` | Установить base_url, js_url, css_url и т.д. |
| `display($content)` | Рендеринг макета с контентом |
| `disableLayout()` / `enableLayout()` | Отключить/включить макет |
| `setTheme($name)` | Сменить тему на лету |
| `setLayoutName($name)` | Сменить файл макета |
| `useThemeCss($file)` | Добавить CSS из темы |
| `useExternalCss($url)` | Добавить внешний CSS |
| `useThemeJs($file, $head?)` | Добавить JS из темы |
| `useExternalJs($url, $head?)` | Добавить внешний JS |
| `setHeader($header)` | Добавить HTTP-заголовок |
| `addPlugin($type, $name, $cb)` | Зарегистрировать плагин движка |
| `getThemeUrl()` / `getThemePath()` | URL/путь текущей темы |
| `getThemeViewsPath()` | Путь к views текущей темы |
| `engineType()` | Тип движка: `'smarty'` или `'native'` |
| `layout()` / `view()` | Экземпляр движка (для обратной совместимости) |

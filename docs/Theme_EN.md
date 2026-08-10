# Theme Class Documentation (Boson Framework)

**Version:** 2.1

Theme and rendering manager. Supports hot-swappable engines (Smarty / Native PHTML) and themes. Single engine instance for both layout and view.

## Configuration (`config.ini`)

```ini
theme  = "smarty"           ; Theme folder in themes/
layout = "layout"           ; Layout file name
cover  = "smarty"           ; Engine: smarty or native (PHTML)

; Security headers (0 to disable)
x_frame_options        = "DENY"
x_content_type_options = "nosniff"
referrer_policy        = "strict-origin-when-cross-origin"
```

## Hot-swap Engine

Change `cover` in `config.ini`:
- `smarty` — `.tpl` templates with `{$var}`, `{if}`, `{i18n}` syntax
- `native` — `.phtml` templates with `<?=$var?>`, `<?php if(): ?>`

Controllers don't need changes — `view('path')` works the same.

## Hot-swap Theme

```php
theme('newtheme'); // switches template folder on the fly
```

## Template Variables

```php
theme()->assign('title', 'My Page');
theme()->assign('users', $users);
```

Always-available globals: `{$base_url}`, `{$js_url}`, `{$css_url}`, `{$images_url}`, `{$content_url}`.

## Dynamic CSS/JS

```php
theme()->useThemeCss('extra.css');
theme()->useThemeJs('widget.js', $head = false);  // false = before </body>
theme()->useExternalJs('https://cdn.example.com/lib.js');
```

Template variables:
- `{$boson_css}` — array of CSS URLs
- `{$boson_js_head}` — array of JS URLs for `<head>`
- `{$boson_js_body}` — array of JS URLs for `<body>`

If the template doesn't use them, regex auto-injection works as fallback.

## Rendering

```php
// In controller:
return view('index/index');               // themes/{theme}/views/index/index.tpl
return view('index/index', ['var' => 1]); // with variables

// JSON without layout:
return json_response(['status' => 'ok']);

// Manual layout disable:
theme()->disableLayout();
echo $content;
```

## Smarty Plugins

Built-in: `{i18n str="key"}`, `{num2word number=n words=['year','years']}`.

Custom:
```php
theme()->addPlugin('function', 'myplugin', 'smarty_function_myplugin');
```

## Flash Messages

```php
session()->flash('message', 'Saved!');
// Next request: $message = session()->flash('message');
// Auto-deleted after read
```

## Security Headers

Sent automatically. Configurable in `config.ini`. Value `0` disables a specific header.

## Methods

| Method | Description |
|---|---|
| `assign($name, $value)` | Template variable |
| `setGlobals()` | Set base_url, js_url, css_url, etc. |
| `display($content)` | Render layout with content |
| `disableLayout()` / `enableLayout()` | Toggle layout |
| `setTheme($name)` | Switch theme |
| `setLayoutName($name)` | Change layout file |
| `useThemeCss($file)` | Add CSS from theme |
| `useExternalCss($url)` | Add external CSS |
| `useThemeJs($file, $head?)` | Add JS from theme |
| `useExternalJs($url, $head?)` | Add external JS |
| `setHeader($header)` | Add HTTP header |
| `addPlugin($type, $name, $cb)` | Register engine plugin |
| `getThemeUrl()` / `getThemePath()` | Theme URL/path |
| `engineType()` | Engine type: `'smarty'` or `'native'` |
| `layout()` / `view()` | Engine instance (backward compat) |

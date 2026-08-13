# Документация класса `Temp` (Boson Framework)

**Версия:** 2.1

Работа с временными файлами. Поддерживает сериализацию любых типов, опциональное шифрование и gzip-сжатие.

## Использование

### Запись

```php
$temp = new Temp('myfile.tmp');
$temp->content($data);     // любой сериализуемый тип (массив, объект, скаляр)
$temp->write();            // сохраняет сериализованные данные
```

### С шифрованием

```php
$temp = new Temp('secure.tmp');
$temp->encryption()
     ->content($secretData)
     ->write();
```

### Чтение

```php
$temp = new Temp('myfile.tmp');
$data = $temp->read();  // автоматически десериализует (и расшифрует, если encryption())
```

### Удаление

```php
$temp->delete();  // true если файл был, false если нет
```

### Проверка существования

```php
if( $temp->exists() ) {
    $data = $temp->read();
}
```

### Смена директории

```php
$temp = new Temp('file.tmp', '/custom/path/');
// или после создания:
$temp->path('/another/path/');

// Полный путь:
echo $temp->filePath();  // /another/path/file.tmp
```

По умолчанию используется `TEMP_DIR`.

## Статические хелперы

### `Temp::create($filename, $content): Temp`
Создать и сразу записать:
```php
$temp = Temp::create('cache.tmp', ['users' => $users, 'count' => 42]);
```

### `Temp::pull($filename): mixed`
Прочитать и сразу удалить:
```php
$data = Temp::pull('oneshot.tmp');
// Файл удалён, данные возвращены
```

## Данные

Содержимое всегда сериализуется через `serialize()`/`unserialize()` — можно хранить любые сериализуемые типы: массивы, объекты (с `__serialize`), строки, числа.

## Шифрование

Метод `encryption()` включает RC4-шифрование через глобальные функции `encrypt()`/`decrypt()`. Должен быть вызван и при записи, и при чтении одного и того же файла.

## Директории

Автоматически создаются при `write()` (через `file_put_gz_content`).

## Исключения

`TempException` — выбрасывается при ошибках записи или если имя файла не указано в конструкторе.

## Методы

| Метод | Описание | Возвращает |
|---|---|---|
| `__construct($name, $dir?)` | Конструктор | `void` |
| `content($value)` | Установить содержимое | `self` |
| `write()` | Записать в файл | `bool` |
| `read()` | Прочитать из файла | `mixed` |
| `delete()` | Удалить файл | `bool` |
| `exists()` | Проверить существование | `bool` |
| `encryption()` | Включить шифрование | `self` |
| `path($dir)` | Сменить директорию | `self` |
| `filePath()` | Полный путь к файлу | `string` |
| `create($name, $content)` | Статический: создать и записать | `Temp` |
| `pull($name)` | Статический: прочитать и удалить | `mixed` |

## Внутренние зависимости

- `file_put_gz_content()` / `file_get_gz_content()` — gzip-сжатие при записи/чтении
- `encrypt()` / `decrypt()` — опциональное шифрование (RC4)
- `path_correct()` — нормализация путей

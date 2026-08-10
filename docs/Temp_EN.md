# Temp Class Documentation (Boson Framework)

**Version:** 2.1

Temporary file storage with serialization, optional encryption, and gzip compression.

## Usage

```php
$temp = new Temp('myfile.tmp');
$temp->content($data);    // any serializable type (array, object, scalar)
$temp->write();           // serialize + gzip + write
```

### With Encryption

```php
$temp = new Temp('secure.tmp');
$temp->encryption()
     ->content($secretData)
     ->write();
```

### Reading

```php
$temp = new Temp('myfile.tmp');
$data = $temp->read();  // auto-deserialize (and decrypt if encryption())
```

### Check & Delete

```php
if ($temp->exists()) {
    $data = $temp->read();
}
$temp->delete();  // true if existed, false if not
```

### Directories

```php
$temp = new Temp('file.tmp', '/custom/path/');
$temp->path('/another/path/');  // change after creation

echo $temp->filePath();  // full path
```

Default: `TEMP_DIR`.

## Static Helpers

```php
// Create and write immediately:
$temp = Temp::create('cache.tmp', ['users' => $users, 'count' => 42]);

// Read and delete in one call:
$data = Temp::pull('oneshot.tmp');
```

## Data

Content is always serialized via `serialize()`/`unserialize()` — supports arrays, objects (with `__serialize`), strings, numbers.

## Encryption

`encryption()` enables RC4 encryption via global `encrypt()`/`decrypt()` functions. Must be called for both write and read of the same file.

## Methods

| Method | Description | Returns |
|---|---|---|
| `__construct($name, $dir?)` | Constructor | `void` |
| `content($value)` | Set content | `self` |
| `write()` | Write to file | `bool` |
| `read()` | Read from file | `mixed` |
| `delete()` | Delete file | `bool` |
| `exists()` | Check existence | `bool` |
| `encryption()` | Enable encryption | `self` |
| `path($dir)` | Change directory | `self` |
| `filePath()` | Full file path | `string` |
| `create($name, $content)` | Static: create + write | `Temp` |
| `pull($name)` | Static: read + delete | `mixed` |

## Dependencies

- `file_put_gz_content()` / `file_get_gz_content()` — gzip
- `encrypt()` / `decrypt()` — optional encryption (RC4)
- `path_correct()` — path normalization

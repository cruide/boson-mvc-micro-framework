# Документация класса `TableCache` (Boson Framework)

**Версия:** 2.1

Кеширование на основе таблицы в БД. Значения сериализуются, поддерживается TTL (время жизни). Встроенный in-memory кеш снижает число запросов к БД.

## Установка

Перед первым использованием нужно создать таблицу:

```php
TableCache::install();
```

Создаёт таблицу `{prefix}cache` в БД.

## Использование

### Глобальные хелперы

```php
// Сохранить (значение или callable)
cache('my_key', $data, 3600);          // на час
cache('my_key', function() {           // ленивое вычисление
    return expensiveQuery();
}, 300);

// Получить и удалить
$data = cache('my_key');               // null если нет или просрочен

// Получить или вычислить и сохранить
$data = cacheRemember('stats', function() {
    return db()->table('orders')->count();
}, 600);
```

### Статические методы

| Метод | Описание |
|---|---|
| `install()` | Создать таблицу кеша |
| `has($key): bool` | Проверить существование (с учётом TTL) |
| `get($key): mixed` | Получить десериализованное значение |
| `put($key, $value, $expire?): mixed` | Сохранить значение |
| `remember($key, $callback, $expire?): mixed` | Получить или вычислить+сохранить |
| `pull($key): mixed` | Получить и удалить |
| `forget($key): void` | Удалить |
| `flush(): void` | Полная очистка |
| `check(): void` | Удалить просроченные записи (GC) |

## Очистка просроченного

```php
TableCache::check(); // удаляет все записи с истёкшим TTL
```

## Уровни кеширования

1. **In-memory** (`$key_has_cache`) — результат `has()` сохраняется в памяти экземпляра. Повторные запросы того же ключа не идут в БД.
2. **БД** — основное хранилище. Значения сериализуются через `serialize()`.

## Таблица

```sql
CREATE TABLE `cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL DEFAULT '',
  `value` mediumtext,
  `expiration` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `expiration` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```

## Исключения

`TableCacheException` — если таблица не создана (вызывается из `has()`).

## Использование в проекте

```php
// Auth.php — счётчик попыток входа
$attempts = (int)cache($key);
cache($key, $attempts + 1, 900);
```

## Глобальные функции

| Функция | Аналог |
|---|---|
| `cache($key, $value?, $ttl?)` | `TableCache::put()` / `TableCache::pull()` |
| `cacheRemember($key, $fn, $ttl?)` | `TableCache::remember()` |

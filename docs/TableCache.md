# TableCache Documentation (Boson Framework)

**Version:** 2.1

Database-backed cache. Values are serialized, with TTL support. Built-in in-memory cache reduces DB queries.

## Installation

```php
TableCache::install();
```

Creates `{prefix}cache` table.

## Usage

### Global Helpers

```php
// Store (value or callable)
cache('my_key', $data, 3600);           // 1 hour
cache('my_key', fn() => expensive(), 300); // lazy

// Retrieve and delete
$data = cache('my_key');                // null if expired/missing

// Get or compute
$data = cacheRemember('stats', fn() => db()->table('orders')->count(), 600);
```

### Static Methods

| Method | Description |
|---|---|
| `install()` | Create cache table |
| `has($key): bool` | Check existence (accounting for TTL) |
| `get($key): mixed` | Get unserialized value |
| `put($key, $value, $expire?): mixed` | Store value |
| `remember($key, $callback, $expire?): mixed` | Get or compute + store |
| `pull($key): mixed` | Get and delete |
| `forget($key): void` | Delete |
| `flush(): void` | Truncate all |
| `check(): void` | Remove expired entries (GC) |

## Cache Levels

1. **In-memory** (`$key_has_cache`) — `has()` result cached in instance. Repeated reads don't hit DB.
2. **Database** — main storage. Values serialized via `serialize()`.

## Table Schema

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

## Exceptions

`TableCacheException` — thrown from `has()` if table not created.

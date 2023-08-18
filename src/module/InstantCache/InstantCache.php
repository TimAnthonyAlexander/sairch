<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\InstantCache;

class InstantCache
{
    public static function get(string $name): mixed
    {
        return $GLOBALS['instantcache'][$name] ?? null;
    }

    public static function set(
        string $name,
        mixed $data
    ): mixed {
        $GLOBALS['instantcache'][$name] = $data;
        return $data;
    }

    public static function delete(
        string $name
    ): void {
        if (self::isset($name)) {
            unset($GLOBALS['instantcache'][$name]);
        }
    }

    public static function isset(
        string $name
    ): bool {
        return isset($GLOBALS['instantcache'][$name]);
    }

    public static function clear(): void
    {
        $GLOBALS['instantcache'] = [];
    }
}

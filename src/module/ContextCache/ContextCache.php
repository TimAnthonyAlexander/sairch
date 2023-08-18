<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\ContextCache;

use TimAlexander\Sairch\module\InstantCache\InstantCache;

class ContextCache extends InstantCache
{
    public static function isset(string $key): bool
    {
        $contextClass = debug_backtrace()[1]['class'];
        $contextClass = str_replace(['\\'], ['_'], $contextClass);
        $contextFunction = debug_backtrace()[1]['function'];

        $key = $contextClass . '_' . $contextFunction . '_' . $key;

        return parent::isset($key);
    }

    public static function get(string $key): mixed
    {
        $contextClass = debug_backtrace()[1]['class'];
        $contextClass = str_replace(['\\'], ['_'], $contextClass);
        $contextFunction = debug_backtrace()[1]['function'];

        $key = $contextClass . '_' . $contextFunction . '_' . $key;

        return parent::get($key);
    }

    public static function set(string $key, mixed $value): mixed
    {
        $contextClass = debug_backtrace()[1]['class'];
        $contextClass = str_replace(['\\'], ['_'], $contextClass);
        $contextFunction = debug_backtrace()[1]['function'];

        $key = $contextClass . '_' . $contextFunction . '_' . $key;

        return parent::set($key, $value);
    }

    public static function delete(string $key): void
    {
        $contextClass = debug_backtrace()[1]['class'];
        $contextClass = str_replace(['\\'], ['_'], $contextClass);
        $contextFunction = debug_backtrace()[1]['function'];

        $key = $contextClass . '_' . $contextFunction . '_' . $key;

        parent::delete($key);
    }
}

<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\Factory;

use TimAlexander\Sairch\module\InstantCache\InstantCache;

class Factory
{
    public static function getClass(
        string $class = Factory::class,
        mixed ...$params
    ): object {
        $paramsHash = md5(json_encode($params, JSON_THROW_ON_ERROR) ?: '');
        $classInstance = InstantCache::isset(sprintf('factories_%s_%s', $class, $paramsHash)) ? clone InstantCache::get(sprintf('factories_%s_%s', $class, $paramsHash)) : new $class(...$params);
        if (!InstantCache::isset(sprintf('factories_%s_%s', $class, $paramsHash))) {
            InstantCache::set(sprintf('factories_%s_%s', $class, $paramsHash), $classInstance);
        }
        assert($classInstance instanceof $class);
        return $classInstance;
    }
}

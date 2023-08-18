<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\DI;

class DIContainer
{
    private array $instances = [];

    public function __construct(array $initialDependencies = [])
    {
        $this->instances = $initialDependencies;
    }

    public function get(
        string $className,
        mixed ...$constructorArguments,
    ): mixed {
        if (!isset($this->instances[$className])) {
            $this->instances[$className] = new $className(...$constructorArguments);
        }

        return $this->instances[$className];
    }
}

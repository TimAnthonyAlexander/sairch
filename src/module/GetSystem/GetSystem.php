<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\GetSystem;

class GetSystem
{
    public readonly int $system;

    public function __construct()
    {
        // Get the system (macOS->1, Windows->2, Linux->3)
        $this->system = $this->getSystem();
    }

    private function getSystem(): int
    {
        $os = PHP_OS;
        if (str_contains($os, 'Darwin') || str_contains($os, 'OSX') || str_contains($os, 'MacOS') || str_contains($os, 'Mac OS')) {
            return 1;
        } elseif (str_contains($os, 'WIN')) {
            return 2;
        } elseif (str_contains($os, 'Linux')) {
            return 3;
        } else {
            return 0;
        }
    }
}

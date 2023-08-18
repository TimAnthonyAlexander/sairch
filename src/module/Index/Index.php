<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\Index;

use TimAlexander\Sairch\module\GetSystem\GetSystem;

class Index
{
    public readonly int $system;

    public function __construct()
    {
        $getSystem = new GetSystem();
        $this->system = $getSystem->system;
    }
}

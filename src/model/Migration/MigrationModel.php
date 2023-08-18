<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\model\Migration;

use TimAlexander\Sairch\model\Data\DataModel;

class MigrationModel extends DataModel
{
    public function __construct(
        public string $do = '',
        public string $undo = '',
        public string $name = '',
    ) {
    }
}

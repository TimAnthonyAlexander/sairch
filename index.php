<?php

declare(strict_types=1);

namespace public;

use TimAlexander\Sairch\module\Index\Index;

require_once __DIR__ . '/vendor/autoload.php';

$index = new Index();
$index->index();

print count($index->files);

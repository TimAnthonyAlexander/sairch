<?php

namespace scripts;

use TimAlexander\Sairch\model\Migration\MigrationModel;
use TimAlexander\Sairch\module\MigrationConfig\MigrationConfig;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MigrationGenerator.php';

$migrationConfig = new MigrationConfig();
print $migrationConfig->executeAllMigrations() . PHP_EOL;

$fixScripts = __DIR__ . '/fixes/';

// Require all files in the fixes folder
foreach (scandir($fixScripts) ?? [] as $file) {
    if (is_file($fixScripts . $file)) {
        require_once $fixScripts . $file;
    }
}

$arg1 = $argv[1] ?? 'execute';

$allModels = MigrationGenerator::getAllModels();

$allMigrations = true;

foreach ($allModels as $model) {
    $migrationGenerator = new MigrationGenerator($model);
    $migrationGenerator->createMigrations();

    foreach ($migrationGenerator->do as $do) {
        $allMigrations = false;

        assert($do instanceof MigrationModel);
        if (trim($arg1) === 'generate') {
            $migrationConfig->addMigration($do->do);
        }
        if (trim($arg1) === 'dry') {
            print $do->do . PHP_EOL;
        }
    }
}

if ($allMigrations) {
    print "No models without migrations." . PHP_EOL;
} else {
    print "Migrations created." . PHP_EOL;
    print "Please re-execute." . PHP_EOL;
}

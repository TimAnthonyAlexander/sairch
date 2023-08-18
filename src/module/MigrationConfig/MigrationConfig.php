<?php

namespace TimAlexander\Sairch\module\MigrationConfig;

use JsonException;
use TimAlexander\Sairch\module\ProjectConfig\ProjectConfig;
use TimAlexander\Sairch\module\QueryBuilder\QueryBuilder;

class MigrationConfig extends ProjectConfig
{
    protected const file = 'config/migrations.json';

    private readonly ExecutedMigrationConfig $executed;

    public function __construct()
    {
        parent::__construct();

        $this->executed = new ExecutedMigrationConfig();
    }

    public function getMigrations(): array
    {
        return $this->getConfigItem('migrations', []);
    }

    public function getExecutedMigrations(): array
    {
        return $this->executed->getConfigItem('executed', []);
    }

    public function getOpenMigrations(): array
    {
        return array_diff($this->getMigrations(), $this->getExecutedMigrations());
    }

    public function addMigration(string $query): void
    {
        $this->config['migrations'][] = $query;
    }

    public function executeMigration(string $query): void
    {

        try {
            QueryBuilder::create()->reset()->customQuery($query)->run();
            $this->executed->config['executed'][] = $query;
        } catch (\PDOException) {
            printf('Migration failed: %s', $query);
        }
    }

    public function executeAllMigrations(): string
    {
        $openMigrations = $this->getOpenMigrations();

        $count = 0;

        foreach ($openMigrations as $migration) {
            $this->executeMigration($migration);
            $count++;
        }

        return sprintf('Executed %d migrations', $count);
    }

    /**
     * @throws JsonException
     */
    public function __destruct()
    {
        $this->writeConfig($this->config);
        $this->executed->writeConfig($this->executed->config);
    }
}

<?php

declare(strict_types=1);

namespace scripts;

use ReflectionClass;
use TimAlexander\Sairch\model\Entity\EntityModel;
use TimAlexander\Sairch\model\Migration\MigrationModel;
use TimAlexander\Sairch\module\DI\DIContainer;
use TimAlexander\Sairch\module\QueryBuilder\QueryBuilder;
use TimAlexander\Sairch\module\SystemConfig\SystemConfig;

class MigrationGenerator
{
    private ReflectionClass $reflectionClass;
    private array $properties = []; // [name => type]
    private array $nullables = [];
    private array $foreignKeys = [];

    public array $do = [];

    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly string $class,
    ) {
    }

    public function generateReflectionClass(): void
    {
        $this->reflectionClass = new ReflectionClass($this->class);
    }

    public function generateProperties(): void
    {
        $this->properties = [];
        foreach ($this->reflectionClass->getProperties() as $property) {
            // Only protected and public properties
            if ($property->isPrivate()) {
                continue;
            }
            if ($property->isStatic()) {
                continue;
            }
            if (in_array($property->getName(), ['unset', 'table'])) {
                continue;
            }
            $this->properties[$property?->getName()] = $property->getType()?->getName();
            if ($property->getType()?->allowsNull() ?? false) {
                $this->nullables[] = $property?->getName();
            }
        }
    }

    public static function convertPHPTypeToMysql8Type(string $type, string $name = ''): string
    {
        $map = [
            'int' => 'bigint',
            'string' => 'longtext',
            'bool' => 'tinyint(1)',
            'float' => 'float',
            'array' => 'json',
            'DateTime' => 'datetime',
        ];

        if (in_array($name, ['created', 'updated', 'lastonline'])) {
            return 'datetime';
        }

        return $map[$type] ?? 'longtext';
    }

    public function default(
        string $type,
        string $name = '',
    ): string {
        $nullable = in_array($name, $this->nullables);

        $type = self::convertPHPTypeToMysql8Type($type, $name);

        if ($type === 'datetime') {
            if ($name === 'updated') {
                return ' DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
            }

            return ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($nullable) {
            return ' DEFAULT NULL';
        }

        if ($type === 'bigint') {
            return ' NOT NULL DEFAULT 0';
        }

        if ($type === 'tinyint(1)') {
            return ' NOT NULL DEFAULT 0';
        }

        if ($type === 'float') {
            return ' NOT NULL DEFAULT 0';
        }

        if ($type === 'json') {
            return ' NOT NULL DEFAULT \'[]\'';
        }

        if ($type === 'longtext') {
            return ' NOT NULL DEFAULT (\'\')';
        }

        return ' NOT NULL';
    }


    public static function convertPHPTypeToInternalMysql8Type(string $type, string $name = ''): string
    {
        $external = self::convertPHPTypeToMysql8Type($type, $name);

        $map = [
            'longtext' => 'longtext',
            'varchar(255)' => 'varchar',
            'datetime' => 'datetime',
            'bigint' => 'bigint',
            'tinyint(1)' => 'tinyint',
            'float' => 'float',
            'json' => 'json',
        ];

        return $map[$external] ?? 'varchar';
    }

    public static function getTableName(string $class): string
    {
        $static = $class::create();
        assert($static instanceof EntityModel);
        return $static::getTableName();
    }

    public function generateCreateStatement(): string
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::getTableName($this->class) . ' (';
        $sql .= 'id varchar(255) NOT NULL, ';
        foreach ($this->properties as $name => $type) {
            if ($name === 'id') {
                continue;
            }
            // Add field
            $sql .= "`{$name}` " . self::convertPHPTypeToMysql8Type($type, $name) . $this->default($type, $name) . ', ';
        }
        $sql .= 'PRIMARY KEY (id)';
        $sql .= ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;';

        return $sql;
    }

    public static function tableExists(string $tableName): bool
    {
        $dicontainer = new DIContainer();
        $database = $dicontainer->get(SystemConfig::class)->getConfigItem('db')['database'] ?? 'coalla';

        $query = QueryBuilder::create()->useDB('information_schema')->selectCount('tables')->where([
            'table_schema' => $database,
            'table_name' => strtolower($tableName),
        ]);

        return $query->run(true, true) > 0;
    }

    public static function modelExists(string $table): bool
    {
        $allModels = self::getAllModels();

        foreach ($allModels as $model) {
            $reflectionClass = new ReflectionClass($model);

            if (!$reflectionClass->isSubclassOf(EntityModel::class)) {
                continue;
            }

            if (self::getTableName($model) === $table) {
                return true;
            }
        }

        return false;
    }

    public static function tableOrModelExists(string $name): bool
    {
        return self::tableExists($name) || self::modelExists($name);
    }

    public static function getAllTables(): array
    {
        $dicontainer = new DIContainer();
        $database = $dicontainer->get(SystemConfig::class)->getConfigItem('db')['database'] ?? 'coalla';

        $query = QueryBuilder::create()->useDB('information_schema')->select('tables')->where([
            'table_schema' => $database,
        ])->run();

        return $query;
    }

    public static function getAllModels(): array
    {
        $models = [];

        foreach (scandir(__DIR__ . '/../src/model') as $file) {
            if (is_dir(__DIR__ . '/../src/model/' . $file) && $file !== '.' && $file !== '..') {
                foreach (scandir(__DIR__ . '/../src/model/' . $file) as $file2) {
                    if (is_file(__DIR__ . '/../src/model/' . $file . '/' . $file2)) {
                        $fileContent = file_get_contents(__DIR__ . '/../src/model/' . $file . '/' . $file2);
                        if (str_contains($fileContent, 'class ' . str_replace('.php', '', $file2))) {
                            $models[] = 'TimAlexander\\Sairch\\model\\' . $file . '\\' . str_replace('.php', '', $file2);
                        }
                    }
                }
            }
        }

        return $models;
    }

    public static function hasTable(string $modelClass): bool
    {
        $reflectionClass = new ReflectionClass($modelClass);

        if (!$reflectionClass->isSubclassOf(EntityModel::class)) {
            return true;
        }

        $table = self::getTableName($modelClass);
        return self::tableExists($table);
    }

    public function generateForeignKeys(): void
    {
        // Foreign keys are when a column of this table is the same name as an existing table name. It is always table.column => column.id
        foreach ($this->properties as $name => $type) {
            if ($name === self::getTableName($this->class)) {
                continue;
            }
            if (self::tableOrModelExists($name)) {
                if (!$this->checkHasForeignKey($name)) {
                    $this->foreignKeys[self::getTableName($this->class)] = $name;
                }
            }
        }
    }

    public function checkHasForeignKey(string $table): bool
    {
        $dicontainer = new DIContainer();
        $database = $dicontainer->get(SystemConfig::class)->getConfigItem('db')['database'] ?? 'coalla';

        $query = QueryBuilder::create()->useDB('information_schema')->selectCount('key_column_usage')->where([
            'table_schema' => $database,
            'table_name' => self::getTableName($this->class),
            'column_name' => $table,
        ]);

        return $query->run(true, true) > 0;
    }

    public static function modifyToVarchar(string $table, string $column): ?MigrationModel
    {
        // First, check if the column is already varchar
        $query = QueryBuilder::create()->useDB('information_schema')->select('columns')->where([
            'table_schema' => 'coalla',
            'table_name' => $table,
            'column_name' => $column,
        ]);

        $result = $query->run(true);

        if ($result['DATA_TYPE'] === 'varchar') {
            return null;
        }

        return new MigrationModel(
            sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` varchar(255) NOT NULL;',
                $table,
                $column,
            ),
            sprintf(
                'ALTER TABLE `%s` MODIFY COLUMN `%s` longtext NOT NULL;',
                $table,
                $column,
            ),
        );
    }

    public static function generateAddForeignKeyStatement(string $table, string $column): MigrationModel
    {
        return new MigrationModel(
            sprintf(
                'ALTER TABLE `%s` ADD FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE CASCADE ON UPDATE CASCADE;',
                $table,
                $column,
                strtolower($column),
            ),
            sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY %s_%s_foreign;',
                $table,
                $table,
                $column,
            ),
        );
    }

    public function generateDropStatement(): string
    {
        return "DROP TABLE IF EXISTS " . self::getTableName($this->class) . ';';
    }

    public function createMigrations(): void
    {
        $this->generateReflectionClass();

        if (!$this->reflectionClass->isSubclassOf(EntityModel::class)) {
            return;
        }

        $this->generateProperties();
        $do = [];

        if (!self::hasTable($this->class)) {
            $migrationModel = new MigrationModel(
                $this->generateCreateStatement(),
                $this->generateDropStatement(),
            );
            $do[] = $migrationModel;
        } else {
            $this->generateForeignKeys();
        }

        foreach ($this->foreignKeys as $table => $column) {
            $do[] = self::modifyToVarchar($column, 'id');
            $do[] = self::modifyToVarchar($table, $column);
            $do[] = self::generateAddForeignKeyStatement($table, $column);
        }

        $do = array_filter($do);

        $do = array_merge($do, $this->generateDifferenceMigrations());

        $this->do = $do;

        #$this->sortDo();
    }

    private function sortDo(): void
    {
        $do = $this->do;
        $creates = [];
        $alters = [];

        foreach ($do as $migrationModel) {
            assert($migrationModel instanceof MigrationModel);

            if (str_contains($migrationModel->do, 'CREATE')) {
                $creates[] = $migrationModel;
            } else {
                $alters[] = $migrationModel;
            }
        }

        $this->do = array_merge($creates, $alters);
    }

    public function generateDifferenceMigrations(): array
    {
        $difference = $this->getDifferenceToTable();

        if ($difference['total'] === 0) {
            return [];
        }

        $do = [];

        foreach ($difference['add'] as $name => $type) {
            $migrationModel = new MigrationModel(
                sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s %s;',
                    self::getTableName($this->class),
                    $name,
                    self::convertPHPTypeToMysql8Type($type, $name),
                    self::default($type, $name),
                ),
                sprintf(
                    'ALTER TABLE `%s` DROP COLUMN `%s`;',
                    self::getTableName($this->class),
                    $name,
                ),
            );
            $do[] = $migrationModel;
        }

        foreach ($difference['remove'] as $name => $type) {
            $migrationModel = new MigrationModel(
                sprintf(
                    'ALTER TABLE `%s` DROP COLUMN `%s`;',
                    self::getTableName($this->class),
                    $name,
                ),
                sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s;',
                    self::getTableName($this->class),
                    $name,
                    self::convertPHPTypeToMysql8Type($type, $name),
                ),
            );
            $do[] = $migrationModel;
        }

        foreach ($difference['change'] as $name => $type) {
            $migrationModel = new MigrationModel(
                sprintf(
                    'ALTER TABLE `%s` MODIFY COLUMN `%s` /*old: %s*/ %s;',
                    self::getTableName($this->class),
                    $name,
                    $type,
                    self::convertPHPTypeToMysql8Type($type, $name),
                ),
            );
            $do[] = $migrationModel;
        }

        foreach ($difference['rename'] as $oldName => $newName) {
            $migrationModel = new MigrationModel(
                sprintf(
                    'ALTER TABLE `%s` RENAME COLUMN `%s` TO %s;',
                    self::getTableName($this->class),
                    $oldName,
                    $newName,
                ),
                sprintf(
                    'ALTER TABLE `%s` RENAME COLUMN `%s` TO %s;',
                    self::getTableName($this->class),
                    $newName,
                    $oldName,
                ),
            );
            $do[] = $migrationModel;
        }

        return $do;
    }

    public function getDifferenceToTable(): array
    {
        $dicontainer = new DIContainer();
        $database = $dicontainer->get(SystemConfig::class)->getConfigItem('db')['database'] ?? 'coalla';

        $query = QueryBuilder::create()->useDB('information_schema')->select('columns')->where([
            'table_schema' => $database,
            'table_name' => self::getTableName($this->class),
        ])->run();

        if (!$this->hasTable($this->class)) {
            return [
                'add' => [],
                'remove' => [],
                'rename' => [],
                'change' => [],
                'total' => 0,
            ];
        }

        $columns = [];
        foreach ($query as $column) {
            $columns[$column['COLUMN_NAME']] = $column;
        }

        $diff = [];

        $diff['add'] = array_diff_key($this->properties, $columns);
        $diff['remove'] = array_diff_key($columns, $this->properties);
        $diff['rename'] = $this->checkForIncorrectCapitalization($diff['add'], $diff['remove']);
        $diff['change'] = [];

        unset($diff['remove']['updated']);
        unset($diff['remove']['created']);

        foreach ($this->properties as $name => $type) {
            if (isset($columns[$name]) && $columns[$name]['DATA_TYPE'] !== self::convertPHPTypeToInternalMysql8Type($type, $name)) {
                // If the internal type is datetime, but the type is string, no change
                if ($columns[$name]['DATA_TYPE'] === 'datetime' && $type === 'string') {
                    continue;
                }
                // If the internal type is longtext, but the type is string, no change
                if ($columns[$name]['DATA_TYPE'] === 'longtext' && $type === 'string') {
                    continue;
                }
                // If the internal type is varchar, but the type is string, no change
                if ($columns[$name]['DATA_TYPE'] === 'varchar' && $type === 'string') {
                    continue;
                }
                $diff['change'][$name] = $type;
            }
        }

        $diff['total'] = count($diff['add']) + count($diff['remove']) + count($diff['change']) + count($diff['rename']);
        return $diff;
    }

    public function checkForIncorrectCapitalization(array &$add, array &$remove): array
    {
        $renames = [];

        foreach ($add as $name => $type) {
            foreach ($remove as $name2 => $type2) {
                if (strtolower($name) === strtolower($name2) || str_contains(strtolower($name2), strtolower($name)) || str_contains(strtolower($name), strtolower($name2))) {
                    unset($add[$name]);
                    unset($remove[$name2]);

                    $renames[$name2] = $name;
                }
            }
        }

        return $renames;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

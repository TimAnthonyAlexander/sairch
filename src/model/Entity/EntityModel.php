<?php

namespace TimAlexander\Sairch\model\Entity;

use Exception;
use ReflectionClass;
use TimAlexander\Sairch\model\Data\DataModel;
use TimAlexander\Sairch\module\ContextCache\ContextCache;
use TimAlexander\Sairch\module\InstantCache\InstantCache;
use TimAlexander\Sairch\module\QueryBuilder\QueryBuilder;

class EntityModel extends DataModel
{
    protected string $table;

    protected array $unset = ['unset'];

    /**
     * @param string $id
     */
    public function __construct(
        public string $id
    ) {
        $this->table = static::getTableName();

        if ($this->id === 'new_') {
            $this->id = base64_encode(uniqid('sairchcreate_', true));
        } else {
            $this->load($this->id);
        }
    }

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        $className = (new ReflectionClass(static::class))->getShortName();
        $className = str_replace('Model', '', $className);
        return strtolower($className);
    }

    /**
     * @return array
     */
    public static function getAll(int $limit = 0, int $offset = 0): array
    {
        if (ContextCache::isset('all')) {
            return ContextCache::get('all');
        }

        $all = QueryBuilder::create()
            ->reset()
            ->select(static::getTableName());

        if ($limit > 0) {
            $all = $all->limit($limit, $offset);
        }

        $all = $all->run();

        ContextCache::set('all', $all);

        return $all;
    }

    /**
     * @param  array  $where
     * @param  bool   $like
     * @param  array  $or
     * @param  array  $startsWith
     * @param  array  $endsWith
     * @param  string $orderBy
     * @param  string $order
     * @param  int    $limit
     * @param  int    $offset
     * @param  array  $columns
     * @param  string $groupBy
     * @return array
     */
    public static function getWhere(
        array $where = [],
        bool|array $like = false,
        array $or = [false],
        array $startsWith = [false],
        array $endsWith = [false],
        string $orderBy = '',
        string $order = 'ASC',
        int $limit = 100,
        int $offset = 0,
        array $columns = ['*'],
        string $groupBy = '',
        string $customOrder = '',
        bool $distinct = false,
    ): array {
        return QueryBuilder::create()
            ->reset()
            ->select(static::getTableName(), $distinct, $columns)
            ->where(
                $where,
                $like,
                $or,
                $startsWith,
                $endsWith,
            )
            ->groupBy($groupBy)
            ->orderBy($orderBy, $order)
            ->orderByCustom($customOrder)
            ->limit($limit, $offset)
            ->run();
    }

    public static function getCount(): false|int
    {
        return QueryBuilder::create()
            ->reset()
            ->selectCount(static::getTableName())
            ->run(fetchFirst: true, fetchFirstFirst: true);
    }

    public static function getCountFor(string $column, string $value): false|int
    {
        return QueryBuilder::create()
            ->reset()
            ->selectCount(static::getTableName())
            ->where([$column => $value])
            ->run(fetchFirst: true, fetchFirstFirst: true);
    }

    public function save(): bool
    {
        $data = $this->toArray();
        unset($data['id'], $data['table']);
        unset($data['updated']);

        foreach ($this->unset as $unset) {
            unset($data[$unset]);
        }

        $success = $this->update($data);
        InstantCache::clear();

        return $success;
    }

    protected function overwriteSave(array $data): bool
    {
        unset($data['id'], $data['table']);
        unset($data['updated']);

        foreach ($this->unset as $unset) {
            unset($data[$unset]);
        }

        $success = $this->update($data);
        InstantCache::clear();

        return $success;
    }

    public static function create(string $id = null): static
    {
        $id ??= 'new_';
        return new static($id);
    }

    public static function createFromData(array $data): static
    {
        $model = new static('new_');
        $model->setData($data);
        return $model;
    }

    /**
     * @param  string $id
     * @return void
     */
    public function load(string $id): void
    {
        if (!static::existsById($id)) {
            return;
        }

        if (InstantCache::isset(self::getTableName() . $id)) {
            $this->setData(InstantCache::get(self::getTableName() . $id));
            return;
        }

        $data = $this->select($id);
        $this->setData($data);
        InstantCache::set(self::getTableName() . $id, $data);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        // $this->createTableIfNotExists();


        $queryBuilder = new QueryBuilder();
        $query = $queryBuilder
            ->reset()
            ->selectCount($this->table)
            ->where(['id' => $this->id])
            ->run(fetchFirst: true, fetchFirstFirst: true);



        return $query > 0;
    }

    public function assertExists(): void
    {
        if (!$this->exists()) {
            throw new Exception('Does not exist');
        }
    }

    /**
     * @param  string $id
     * @return bool
     */
    public static function existsById(string $id): bool
    {


        if (ContextCache::isset($id . static::getTableName())) {
            return ContextCache::get($id . static::getTableName());
        }

        $queryBuilder = new QueryBuilder();
        $query = $queryBuilder
            ->reset()
            ->selectCount(static::getTableName())
            ->where(['id' => $id])
            ->run(fetchFirst: true, fetchFirstFirst: true);

        ContextCache::set($id . static::getTableName(), $query > 0);



        return $query > 0;
    }

    /*
    private function createTableIfNotExists(): void
    {
        $migrations = new MigrationConfig();
        $migrations->executeAllMigrations();

        // Fallback if migrations do not work
        $queryBuilder = new QueryBuilder();
        try {
            $queryBuilder->reset()->select($this->table)->run();
        } catch (\PDOException) {
            $defaultColumn = [
                'name' => 'id',
                'type' => 'int',
                'null' => false,
                'auto_increment' => false,
                'default' => null,
                'primary_key' => false,
            ];

            // Reflectionclass get properties, but remove table
            $properties = (new \ReflectionClass($this))->getProperties();
            $columns = [];
            foreach ($properties as $property) {
                if ($property->getName() === 'table') {
                    continue;
                }
                // Change the type according to the property type
                $type = match ($property->getType()?->getName()) {
                    'int' => 'int',
                    'string' => 'varchar(255)',
                    'bool' => 'tinyint(1)',
                    'float' => 'float',
                    default => 'text',
                };

                $column = $defaultColumn;
                $column['type'] = $type;
                $column['name'] = $property->getName();

                if ($property->getName() === 'id') {
                    $column['primary_key'] = true;
                }

                $column['default'] = $property->hasDefaultValue() ? $property->getDefaultValue() : match ($type) {
                    'int', 'tinyint(1)' => 0,
                    'float' => 0.0,
                    default => '',
                };

                $columns[] = $column;
            }

            $queryBuilder->reset()->createTable($this->table, $columns)->run();
        }
    }
    */

    /**
     * @return bool
     */
    public function delete(): bool
    {
        $queryBuilder = new QueryBuilder();
        $return = $queryBuilder
            ->reset()
            ->delete($this->table)
            ->where(['id' => $this->id])
            ->run();

        InstantCache::clear();

        return $return !== false;
    }

    protected function deleteWhere(string $column, mixed $value): bool
    {
        $queryBuilder = new QueryBuilder();
        $return = $queryBuilder
            ->reset()
            ->delete($this->table)
            ->where([$column => $value])
            ->run();

        InstantCache::clear();

        return $return !== false;
    }

    /**
     * @param  array $data
     * @return bool
     */
    private function update(array $data): bool
    {
        if (!$this->exists()) {
            return $this->insert($data);
        }

        $queryBuilder = new QueryBuilder();
        $return = $queryBuilder
            ->reset()
            ->update($this->table, $data)
            ->where(['id' => $this->id])
            ->run();

        InstantCache::clear();

        return $return !== false;
    }

    /**
     * @param  array $data
     * @return bool
     */
    private function insert(array $data): bool
    {
        $data['id'] = $this->id;

        $queryBuilder = new QueryBuilder();
        $return = $queryBuilder
            ->reset()
            ->insert($this->table, $data)
            ->run();

        InstantCache::clear();

        return $return !== false;
    }

    /**
     * @param  string $id
     * @return array
     */
    private function select(string $id): array
    {
        $queryBuilder = new QueryBuilder();
        return $queryBuilder
            ->reset()
            ->select($this->table)
            ->where(['id' => $id])
            ->run(fetchFirst: true);
    }

    /**
     * @param  string $column
     * @param  string $value
     * @return static|null
     */
    public static function getBy(
        string $column,
        string $value,
    ): ?static {
        $queryBuilder = new QueryBuilder();
        $id = $queryBuilder
            ->reset()
            ->select(static::getTableName(), true, ['id'])
            ->where([$column => $value])
            ->limit(1)
            ->run(true, true);

        if (is_array($id)) {
            return null;
        }

        if ($id === null) {
            return null;
        }

        return new static($id);
    }

    /**
     * @param  array $data
     * @return void
     */
    public function setData(array $data): void
    {
        // Set the properties
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }
    }

    public static function createFromArray(array $data): static
    {
        $object = static::create();
        $object->setData($data);
        return $object;
    }

    /**
     * @param  string $field
     * @param  string $content
     * @return bool
     */
    protected static function existsByCustom(string $field, string $content): bool
    {
        $queryBuilder = new QueryBuilder();
        $query = $queryBuilder
            ->reset()
            ->selectCount(static::getTableName())
            ->where([$field => $content])
            ->run(fetchFirst: true, fetchFirstFirst: true);

        return $query > 0;
    }

    /**
     * This returns a new static by the given field and content
     *
     * @param  string $field
     * @param  string $content
     * @return static|null
     */
    protected static function getFirstByCustom(string $field, string $content): ?static
    {
        $queryBuilder = new QueryBuilder();
        $id = $queryBuilder
            ->reset()
            ->select(static::getTableName(), true, ['id'])
            ->where([$field => $content])
            ->limit(1)
            ->run(true, true);

        if ($id === null) {
            return null;
        }

        if (!is_string($id)) {
            return null;
        }

        return new static($id);
    }

    /**
     * This returns an array of new statics by the given field and content
     *
     * @param  string $field
     * @param  string $content
     * @return array<static>
     */
    protected static function getAllByCustom(
        string $field,
        string|null $content,
        bool $sortByCreated = false,
        bool $sortByUpdated = false,
        int $page = 0,
        int $perPage = 10,
        bool $useCache = true,
        bool $createModels = true,
    ): array {
        $select = $createModels ? ['*'] : ['id', 'created', 'updated'];

        // Remove al question marks from content
        if (str_contains((string) $content, '?')) {
            $content = str_replace('?', '', (string) $content);
        }

        $queryBuilder = new QueryBuilder();
        $query = $queryBuilder
            ->reset()
            ->select(static::getTableName(), true, $select)
            ->where([$field => $content]);

        if ($sortByCreated) {
            $query = $query->orderBy('created', 'DESC');
        }

        if ($sortByUpdated) {
            $query = $query->orderBy('updated', 'DESC');
        }

        if ($page > 0) {
            $query = $query->limit($perPage, ($page - 1) * $perPage);
        }

        $rows = $query->run(useCache: $useCache);

        if ($createModels) {
            $models = [];
            foreach ($rows as $row) {
                $models[] = static::createFromData($row);
            }
            return $models;
        }

        return $rows;
    }

    protected static function getCertainFieldsByCustom(
        string $field,
        string $content,
        array $fields,
    ): array {
        $queryBuilder = new QueryBuilder();
        return $queryBuilder
            ->reset()
            ->select(static::getTableName(), true, $fields)
            ->where([$field => $content])
            ->run();
    }
}

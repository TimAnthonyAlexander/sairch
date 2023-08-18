<?php

/*
 * Copyright (c) 2022. Der Code ist geistiges Eigentum von Tim Anthony Alexander.
 * Der Code wurde geschrieben unter dem Arbeitstitel und im Auftrag von coalla-api.
 * Verwendung dieses Codes außerhalb von coalla-api von Dritten ist ohne ausdrückliche Zustimmung von Tim Anthony Alexander nicht gestattet.
 */

namespace TimAlexander\Sairch\module\QueryBuilder;

use Exception;
use PDOException;
use RuntimeException;
use TimAlexander\Sairch\module\BetterSQL\BetterSQL;
use TimAlexander\Sairch\module\DI\DIContainer;
use TimAlexander\Sairch\module\InstantCache\InstantCache;
use TimAlexander\Sairch\module\PrettyJson\PrettyJson;
use TimAlexander\Sairch\module\SystemConfig\SystemConfig;

/**
 * @copyright Tim Anthony Alexander @ coalla-api
 */
class QueryBuilder
{
    private const REPLACEMENT_STRING = '¢[]|';

    /**
     * @var string
     */
    public string $query = '';

    private bool $select = false;

    private string $currentTable = '';

    private bool $debug = false;

    public static function create(): self
    {
        return new self();
    }

    /**
     * @return $this
     * @param  array<int,mixed> $columns
     */
    public function select(
        string $table,
        bool $distinct = false,
        array $columns = ['*'],
        bool $quotify = true,
    ): QueryBuilder {
        $this->select = true;
        $this->currentTable = $table;
        $verb = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        $columns = $quotify ? self::quotify(true, $columns) : $columns;

        $return = sprintf('%s %s FROM `%s` ', $verb, implode(', ', $columns), $table);
        // Replace all . with `.`
        $return = str_replace('.', '`.`', $return);

        return $this->withQuery(str_replace('`*`', '*', $return));
    }

    /**
     * @param array<int,mixed> $columns
     */
    public function selectCount(string $table, bool $distinct = false, array $columns = ['*']): QueryBuilder
    {
        $this->select = true;
        $this->currentTable = $table;
        $columns = self::quotify(true, $columns);
        return $this->withQuery(sprintf('SELECT COUNT(%s) FROM `%s` ', implode(', ', $columns), $table));
    }

    /**
     * @return $this
     */
    public function reset(): self
    {
        $this->select = false;
        $this->currentTable = '';
        $this->query = '';

        return $this;
    }

    public function debug(): self
    {
        $this->debug = true;
        return $this;
    }
    /**
     * @param array<int,mixed> $columns
     */
    public function alterTable(
        string $table,
        array $columns = [
            ['name' => 'type']
        ]
    ): QueryBuilder {
        $this->select = false;
        $this->currentTable = $table;
        $this->query = sprintf('ALTER TABLE `%s` ', $table);
        foreach ($columns as $column) {
            $this->query .= sprintf('ADD COLUMN `%s` %s ', $column['name'], $column['type']);
        }
        return $this;
    }
    /**
     * @param array<int,mixed> $columns
     */
    public function createTable(
        string $table,
        array $columns = [
            [
                'name' => 'id',
                'type' => 'int',
                'null' => false,
                'auto_increment' => true,
                'default' => null,
                'primary_key' => true,
            ]
        ]
    ): QueryBuilder {
        $this->currentTable = $table;
        $this->query = sprintf('CREATE TABLE `%s` (', $table);
        foreach ($columns as $column) {
            $this->query .= sprintf(
                '`%s` %s %s %s %s %s, ',
                $column['name'],
                $column['type'],
                $column['null'] ? 'NULL' : 'NOT NULL',
                $column['auto_increment'] ? 'AUTO_INCREMENT' : '',
                $column['default'] ? 'DEFAULT ' . $column['default'] : '',
                $column['primary_key'] ? 'PRIMARY KEY' : ''
            );
        }
        $this->query = rtrim($this->query, ', ');
        $this->query .= ')';

        return $this;
    }

    public function isSelect(): bool
    {
        return $this->select;
    }
    /**
     * @param array<int,mixed> $elements
     */
    private static function buildSetString(array $elements): string
    {
        $set = [];
        foreach ($elements as $key => $value) {
            $set[] = match (true) {
                is_null($value) => sprintf('`%s` = NULL', $key),
                is_bool($value) => sprintf('`%s` = %s', $key, $value ? 1 : 0),
                is_int($value) => sprintf('`%s` = %d', $key, $value),
                str_contains((string) $value, "'") => sprintf('`%s` = \'%s\'', $key, str_replace("'", "''", (string) $value)),
                default => sprintf("`%s` = '%s'", $key, $value),
            };
        }
        return implode(', ', $set);
    }

    /**
     * @return $this
     * @param  array<int,mixed> $elements
     */
    public function update(string $table, array $elements = ['column1' => 'value1', 'column2' => 'value2']): QueryBuilder
    {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $this->currentTable = $table;
        return $this->withQuery(sprintf('UPDATE `%s` ', $table) . sprintf('SET %s ', self::buildSetString($elements)));
    }

    /**
     * @return $this
     */
    private function withQuery(string $query, string ...$replacements): self
    {
        foreach ($replacements as $replacement) {
            $pos = strpos($query, self::REPLACEMENT_STRING);
            if ($pos !== false) {
                $query = substr_replace($query, $replacement, $pos, strlen(self::REPLACEMENT_STRING));
            }
        }
        $this->query .= $query;
        return $this;
    }

    /**
     * @return $this
     */
    public function delete(
        string $table,
    ): self {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $this->currentTable = $table;
        return $this->withQuery(sprintf('DELETE FROM `%s` ', $table));
    }

    public function customQuery(
        string $query,
    ): self {
        $this->currentTable = '';
        return $this->withQuery($query);
    }

    /**
     * @param  string|null      $custom
     * @param  string|null      $customColumns
     * @return $this
     * @param  array<int,mixed> $elements
     */
    public function insert(string $table, array $elements = ['column1' => 'value1', 'column2' => 'value2'], bool $replace = true, string $custom = null, string $customColumns = null): QueryBuilder
    {
        $this->currentTable = $table;
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $verb = $replace ? 'REPLACE' : 'INSERT';
        if ($custom !== null) {
            return $this->withQuery(
                sprintf('%s INTO %s (%s) VALUES %s', $verb, $table, $customColumns, $custom)
            );
        }
        $columns = self::quotify(true, array_keys($elements));
        $columnsString = implode(', ', $columns);
        $values = self::quotify(false, array_values($elements));
        $valuesString = implode(', ', $values);
        return $this->withQuery(
            self::REPLACEMENT_STRING . ' INTO `' . self::REPLACEMENT_STRING . '` (' . self::REPLACEMENT_STRING . ') VALUES (' . self::REPLACEMENT_STRING . ') ',
            $verb,
            $table,
            $columnsString,
            $valuesString
        );
    }
    /**
     * @param  array<int,mixed> $elements
     * @return 1|"*"|"NULL"|string[]|array
     */
    public static function quotify(
        bool $isColumn,
        array $elements = ['value1', 'value2'],
    ): array {
        $values = array_values($elements);
        // Unset null values
        if ($isColumn) {
            $return = [];
            foreach ($values as $value) {
                if ($value === '?') {
                    throw new RuntimeException('Columns cannot contain ?');
                }
                $return[] = in_array($value, [1, '*', 'NULL'], true)
                    ? $value
                    : sprintf("`%s`", $value);
            }
            return $return;
        }
        return array_map(
            static fn ($value) => match (true) {
                $value === "null" => 'NULL',
                is_bool($value) => $value ? 'TRUE' : 'FALSE',
                is_int($value) => $value,
                ($value === '?') => $value,
                str_contains((string) $value, "'") => sprintf("'%s'", str_replace("'", "''", (string) $value)),
                is_null($value) => 'NULL',
                default => sprintf("'%s'", $value),
            },
            $values
        );
    }

    /**
     * @return $this
     */
    public function alter(
        string $table,
    ): self {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        return $this->withQuery('ALTER TABLE ' . sprintf('`%s` ', $table));
    }

    /**
     * @return $this
     */
    public function add(
        string $column,
        string $type,
    ): self {
        return $this->withQuery(sprintf('ADD `%s` %s ', $column, $type));
    }

    /**
     * @return $this
     * @param  array<string, mixed> $elements
     * @param  array<bool>          $or
     * @param  array<bool>          $startsWith
     * @param  array<bool>          $endsWith
     */
    public function where(
        array $elements = ['column1' => 'value1', 'column2' => 'value2'],
        bool|array $like = false,
        array $or = [false],
        array $startsWith = [false, false],
        array $endsWith = [false, false],
        bool $custom = false,
        string $customString = '',
        bool $inBrackets = false,
        bool $lower = false,
        bool $not = false,
        array $brackets = [],
        bool $totallyInBrackets = false,
        bool $isJoin = false,
        array $startBrackets = [],
        array $endBrackets = [],
    ): QueryBuilder {
        if ($custom) {
            return $this->withQuery(sprintf('WHERE %s ', $customString));
        }
        $first = true;
        $index = 0;
        foreach ($elements as $key => $value) {
            if (!is_array($value)) {
                $startingBracket = $totallyInBrackets && $index === 0;
                $endingBracket = $totallyInBrackets && $index === count($elements) - 1;

                if ($startBrackets[$index] ?? false) {
                    $startingBracket = true;
                }
                if ($endBrackets[$index] ?? false) {
                    $endingBracket = true;
                }

                $this->advancedWhere(
                    $key,
                    $value,
                    is_array($like) ? $like[$index] : $like,
                    $first,
                    $or[$index] ?? false,
                    $startsWith[$index] ?? false,
                    $endsWith[$index] ?? false,
                    inBrackets: $brackets[$index] ?? false,
                    totallyInBrackets: $inBrackets,
                    lower: $lower,
                    not: $not,
                    startBracket: $startingBracket,
                    endBracket: $endingBracket,
                    isJoin: $isJoin,
                );
            } else {
                $this->whereIn($key, $value, $first, $or[$index] ?? false);
            }
            $first = false;
            $index++;
        }
        return $inBrackets ? $this->withQuery(') ') : $this;
    }

    /**
     * @param  string|null $customOperator
     * @return $this
     */
    public function advancedWhere(
        string $column,
        ?string $value,
        bool $like = false,
        bool $isFirstWhereInQuery = true,
        bool $or = false,
        bool $startsWith = false,
        bool $endsWith = false,
        bool $custom = false,
        string $customString = '',
        string $customOperator = null,
        bool $inBrackets = false,
        bool $totallyInBrackets = false,
        bool $lower = false,
        bool $not = false,
        bool $startBracket = false,
        bool $endBracket = false,
        bool $removeQuotes = false,
        bool $isJoin = false,
    ): self {
        if ($custom) {
            if (!$removeQuotes) {
                return $this->withQuery(sprintf('WHERE `%s` ', $customString));
            } else {
                return $this->withQuery(sprintf('WHERE %s ', $customString));
            }
        }
        if ($like) {
            if (!($startsWith)) {
                $value = '%' . $value;
            }
            if (!($endsWith)) {
                $value .= '%';
            }
        }
        $operator = $isFirstWhereInQuery
            ? 'WHERE'
            : ($or
                ? 'OR'
                : 'AND');
        $operator = $totallyInBrackets ? $operator . ' (' : $operator;
        $likeoperator = $like ? 'LIKE' : ($not ? '!=' : '=');
        if ($customOperator) {
            $likeoperator = $customOperator;
        }
        $isOperator = $not ? 'IS NOT' : 'IS';
        $where = match (true) {
            is_null($value) => sprintf($lower ? 'LOWER(`%s`) %s NULL' : '`%s` %s NULL', $column, $isOperator),
            str_starts_with($value, 'COMMAND') && str_ends_with($value, 'COMMAND') => sprintf(
                $lower ? 'LOWER(`%s`) %s' : '`%s` %s',
                $column,
                str_replace('COMMAND', '', $value)
            ),
            str_contains($value, '?') => sprintf(
                $lower ? 'LOWER(`%s`) %s %s' : '`%s` %s %s',
                $column,
                $likeoperator,
                $value
            ),
            str_contains($value, "'") => sprintf(
                $lower ? 'LOWER(`%s`) %s \'%s\'' : '`%s` %s \'%s\'',
                $column,
                $likeoperator,
                str_replace("'", "''", $value)
            ),
            default => sprintf($lower ? "LOWER(`%s`) %s '%s'" : "`%s` %s '%s'", $column, $likeoperator, $value),
        };
        if ($inBrackets) {
            $where = sprintf('(%s)', $where);
        } elseif ($startBracket) {
            $where = sprintf('(%s', $where);
        } elseif ($endBracket) {
            $where = sprintf('%s)', $where);
        }
        if ($isJoin) {
            $where = str_replace('`', '', $where);
        }
        return $this->withQuery(sprintf('%s %s ', $operator, $where));
    }

    /**
     * @param array<int,mixed> $values
     */
    public function whereIn(string $key, array $values, bool $first = true, bool $or = false): QueryBuilder
    {
        $values = self::quotify(false, $values);
        $valuesString = implode(', ', $values);

        return $first
            ? $this->withQuery(sprintf(' WHERE %s IN (%s) ', $key, $valuesString))
            : ($or
                ? $this->withQuery(sprintf(' OR `%s` IN (%s) ', $key, $valuesString))
                : $this->withQuery(sprintf(' AND `%s` IN (%s) ', $key, $valuesString))
            );
    }

    public function joinAll(
        array|string $otherTable,
        array|string $type = 'INNER',
        array $on = [
            'table1.column1',
            'table2.column2',
        ],
        string|array $where = [],
        bool $first = true,
        array|bool $or = false,
        array $like = [false],
        array $startsWith = [true],
        array $endsWith = [true],
        array $openBrackets = [false],
        array $closeBrackets = [false],
    ): QueryBuilder {
        if (is_array($otherTable)) {
            $i = 0;
            $j = 0;
            foreach ($otherTable as $table) {
                $this->withQuery(sprintf('%s JOIN `%s` ', $type[$j] ?? $type, $table));

                $this->on($on[$i], $on[$i + 1]);
                $i = $i + 2;
                $j++;
            }
        } else {
            assert(is_string($otherTable));
            assert(is_string($type));
            $this->withQuery(sprintf('%s JOIN `%s` ', (string) $type, (string) $otherTable));

            $this->on($on[0], $on[1]);
        }

        $first = true;

        $i = 0;
        if (is_array($where)) {
            foreach ($where as $key => $value) {
                $this->advancedWhere(
                    $key,
                    $value,
                    isFirstWhereInQuery: $first,
                    or: is_array($or) ? $or[$i] ?? false : $or,
                    isJoin: true,
                    like: $like[$i] ?? false,
                    startsWith: $startsWith[$i] ?? true,
                    endsWith: $endsWith[$i] ?? true,
                    startBracket: $openBrackets[$i] ?? false,
                    endBracket: $closeBrackets[$i] ?? false,
                );
                $first = false;
                $i++;
            }
        } else {
            $this->withQuery($where);
        }
        return $this;
    }


    public function joinAdvanced(
        string $otherTable,
        string $type = 'INNER',
        array $on = [
            'table1.column1',
            'table2.column2',
        ],
        array $elements = ['column1' => 'value1', 'column2' => 'value2'],
        bool|array $like = false,
        array $or = [false],
        array $startsWith = [false, false],
        array $endsWith = [false, false],
        bool $lower = true,
    ): QueryBuilder {
        $this->withQuery(sprintf('%s JOIN %s ', $type, $otherTable));

        $this->on($on[0], $on[1]);

        $this->where(
            elements: $elements,
            like: $like,
            or: $or,
            startsWith: $startsWith,
            endsWith: $endsWith,
            lower: $lower,
            isJoin: true,
        );
        return $this;
    }


    private function on(
        string $one,
        string $two,
    ): QueryBuilder {
        $return = sprintf(
            'ON `%s` = `%s` ',
            $one,
            $two,
        );

        $return = str_replace('.', '`.`', $return);

        return $this->withQuery($return);
    }

    /**
     * @return $this
     */
    public function bracket(string $bracket = '('): self
    {
        return $this->withQuery(sprintf('%s ', $bracket));
    }

    /**
     * @return $this
     */
    public function orderBy(string $order = '', string $oderby = 'DESC'): self
    {
        if ($order === '') {
            return $this;
        }

        if (str_contains($order, '.')) {
            $order = str_replace('.', '`.`', $order);
        }

        return $this->withQuery('ORDER BY `' . self::REPLACEMENT_STRING . '` ' . self::REPLACEMENT_STRING . ' ', $order, $oderby);
    }

    public function orderByCustom(string $order = ''): self
    {
        if ($order === '') {
            return $this;
        }

        return $this->withQuery('ORDER BY ' . self::REPLACEMENT_STRING . ' ', $order);
    }

    /**
     * @return $this
     */
    public function limit(
        string|int $limit = 1,
        string|int $offset = 0,
    ): self {
        return $this->withQuery('LIMIT ' . self::REPLACEMENT_STRING . ' OFFSET ' . self::REPLACEMENT_STRING . ' ', (string) $limit, (string) $offset);
    }

    public function groupBy(string $groupby = ''): self
    {
        if ($groupby === '') {
            return $this;
        }

        if (str_contains($groupby, '.')) {
            $groupby = str_replace('.', '`.`', $groupby);
        }

        return $this->withQuery('GROUP BY `' . self::REPLACEMENT_STRING . '` ', $groupby);
    }

    public static function unsetNumKeys(array $data): array
    {
        $array_out = [];
        if (array_is_list($data)) {
            foreach ($data as $k => $v) {
                $array_out[$k] = self::unsetNumKeys($v);
            }
            return $array_out;
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $array_out[$k] = self::unsetNumKeys($v);
            } elseif (!is_numeric($k)) {
                $array_out[$k] = $v;
            }
        }
        return $array_out;
    }

    /**
     * @param array<int,mixed> $args
     */
    public function run(
        bool $fetchFirst = false,
        bool $fetchFirstFirst = false,
        array $args = [],
        bool $debug = false,
        bool $useCache = true,
    ): mixed {
        if ($debug || $this->debug) {
            var_dump($this->query);
        }

        $uuid = self::generateUuid($args, $this->query, $fetchFirst, $fetchFirstFirst);

        $query = $GLOBALS['queries'][$uuid]['query'] = $this->build($args);

        $GLOBALS['queries'][$uuid]['args'] = $args;
        $GLOBALS['queries'][$uuid]['uuid'] = $uuid;

        if (!isset($GLOBALS['countsforquery'][$query])) {
            $GLOBALS['countsforquery'][$query] = 1;
        } else {
            $GLOBALS['countsforquery'][$query]++;
        }

        $GLOBALS['queries'][$uuid]['count'] = $GLOBALS['countsforquery'][$query];

        if (!isset($GLOBALS['queries'][$uuid]['totalms'])) {
            $GLOBALS['queries'][$uuid]['totalms'] = 0;
        }

        $before = microtime(true);

        $return = $this->cachedExecute($fetchFirst, $fetchFirstFirst, $args, $query, $useCache);

        $total = round((microtime(true) - $before) * 1000);
        $GLOBALS['queries'][$uuid]['totalms'] += $total;

        return $return;
    }
    /**
     * @param array<int,mixed> $args
     */
    private static function generateUuid(array $args, string $query, bool $fetchFirst, bool $fetchFirstFirst): string
    {
        $fetchString = $fetchFirst ? 'fetchFirst' : 'fetchAll' . ($fetchFirstFirst ? 'First' : 'Not');
        return md5($query . PrettyJson::encode($args) . $fetchString);
    }
    /**
     * @param array<int,mixed> $args
     */
    private function cachedExecute(
        bool $fetchFirst = false,
        bool $fetchFirstFirst = false,
        array $args = [],
        string $query = '',
        bool $useCache = true,
    ): mixed {
        $uuid = self::generateUuid($args, $query, $fetchFirst, $fetchFirstFirst);

        if (!isset($GLOBALS['UPDATEDTABLES'])) {
            $GLOBALS['UPDATEDTABLES'] = [];
        }

        if ($useCache && InstantCache::isset($uuid) && $this->isSelect() && !in_array(strtolower(trim($this->currentTable)), $GLOBALS['UPDATEDTABLES'], true)) {
            return InstantCache::get($uuid);
        }

        $beforeActual = microtime(true);
        try {
            $return = BetterSQL::runQuery($this, $fetchFirst, $fetchFirstFirst, $args);
        } catch (PDOException $e) {
            throw $e;
        }
        $totalActual = round((microtime(true) - $beforeActual) * 1000);
        if (isset($GLOBALS['queries'][$uuid]['totalmsactual'])) {
            $GLOBALS['queries'][$uuid]['totalmsactual'] += $totalActual;
        } else {
            $GLOBALS['queries'][$uuid]['totalmsactual'] = $totalActual;
        }

        InstantCache::set($uuid, $return);

        return $return;
    }

    public function dropDB(string $db): self
    {
        $this->query = 'DROP DATABASE IF EXISTS `' . $db . '`';
        $this->run();

        return $this;
    }

    public function createDB(string $db): self
    {
        $this->query = 'CREATE DATABASE IF NOT EXISTS `' . $db . '`';
        $this->run();

        return $this;
    }

    public function useDB(string $db): self
    {
        $this->query = 'USE `' . $db . '`';
        $this->run();
        $this->query = '';

        return $this;
    }

    public function useDefaultDB(): self
    {
        $dicontainer = new DIContainer();
        $systemConfig = $dicontainer->get(SystemConfig::class);
        assert($systemConfig instanceof SystemConfig);

        $defaultDB = $systemConfig->getConfigItem('db')['database'];
        $this->query = 'USE `' . $defaultDB . '`';

        $this->run();

        $this->reset();

        return $this;
    }

    public function createUser(
        string $user,
        string $password,
    ): self {
        $this->query = 'CREATE USER IF NOT EXISTS `' . $user . '`@\'127.0.0.1\' IDENTIFIED BY \'' . $password . '\'';
        $this->run();

        return $this;
    }

    public function grantAllPrivileges(
        string $user,
    ): self {
        $this->query = 'GRANT ALL PRIVILEGES ON `%`.* TO `' . $user . '`@\'127.0.0.1\'';
        $this->run();

        return $this;
    }

    public static function sortQueries(): void
    {
        if (isset($GLOBALS['queries'])) {
            foreach ($GLOBALS['queries'] as $uuid => $query) {
                if (!isset($GLOBALS['queries'][$uuid]['uuid'])) {
                    unset($GLOBALS['queries'][$uuid]);
                    continue;
                }
                if (!isset($GLOBALS['queries'][$uuid]['totalms'])) {
                    $GLOBALS['queries'][$uuid]['totalms'] = 0;
                }
            }
            usort($GLOBALS['queries'], static fn ($a, $b): int => ($a['totalms'] > $b['totalms']) ? -1 : 1);
        }
    }

    public function setSelect(bool $select): void
    {
        $this->select = $select;
    }

    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * This method builds the final
     *
     * @throws Exception
     * @param  array<int,mixed> $values
     */
    public function build(array $values = []): string
    {
        if ($values !== []) {
            $count = substr_count($this->query, '?');
            if (count($values) !== $count) {
                throw new Exception('Not enough values in the array: ' . $this->query . ' ' . count($values) . ' ' . $count . ' ' . json_encode($values, JSON_THROW_ON_ERROR));
            }

            $query = str_replace(['%', '?'], [self::REPLACEMENT_STRING, '%s'], $this->query);
            $query = sprintf($query, ...$values);
            $query = str_replace(self::REPLACEMENT_STRING, '%', $query);

            $query = trim($query);
            return trim($query, ';') . ';';
        }

        return trim(trim($this->query), ';') . ';';
    }
}

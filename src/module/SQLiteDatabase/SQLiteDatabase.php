<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\SQLiteDatabase;

use Exception;
use PDO;
use PDOException;

class SQLiteDatabase
{
    private PDO $pdo;

    /**
     * Constructor to initialize SQLite database connection.
     * It will create a new SQLite file if it doesn't exist.
     *
     * @param string $filename
     * @throws Exception
     */
    public function __construct(string $filename = 'database.sqlite')
    {
        if (!file_exists($filename)) {
            $file = fopen($filename, 'w');
            if ($file === false) {
                throw new Exception('Could not create SQLite file.');
            }
            fclose($file);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $filename);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a given SQL command.
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function execute(string $sql, array $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Query execution failed: ' . $e->getMessage());
        }
    }

    public function tableExists(string $tableName): bool
    {
        $sql = "SELECT count(*) FROM sqlite_master WHERE type='table' AND name=:tableName";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tableName' => $tableName]);
        return $stmt->fetchColumn() > 0;
    }

    public function getColumns(string $tableName): array
    {
        if (!$this->tableExists($tableName)) {
            throw new Exception('Table does not exist.');
        }

        $sql = "PRAGMA table_info(:tableName)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tableName' => $tableName]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = $column['name'];
        }

        return $columns;
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }
}

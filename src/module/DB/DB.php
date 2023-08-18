<?php

namespace TimAlexander\Sairch\module\DB;

use PDOException;
use TimAlexander\Sairch\module\DI\DIContainer;
use TimAlexander\Sairch\module\SystemConfig\SystemConfig;

class DB
{
    private static ?\PDO $pdoDriverInstance = null;

    public static function getDriver(): \PDO
    {
        if (self::$pdoDriverInstance !== null) {
            return self::$pdoDriverInstance;
        }

        $container = new DIContainer();
        $systemConfig = $container->get(SystemConfig::class);

        $systemConfig->getConfigItem(
            'db',
            [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3999',
                'database' => 'sairch',
                'username' => 'dev',
                'password' => 'development33!',
            ]
        );

        if (isset($systemConfig->getConfigItem('db')['socket'])) {
            $dsn = sprintf(
                '%s:unix_socket=%s;dbname=%s',
                $systemConfig->getConfigItem('db')['driver'],
                $systemConfig->getConfigItem('db')['socket'],
                $systemConfig->getConfigItem('db')['database']
            );
        } else {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s',
                $systemConfig->getConfigItem('db')['driver'],
                $systemConfig->getConfigItem('db')['host'],
                $systemConfig->getConfigItem('db')['port'],
                $systemConfig->getConfigItem('db')['database']
            );
        }

        try {
            $pdoDriver = new \PDO(
                $dsn,
                $systemConfig->getConfigItem('db')['username'],
                $systemConfig->getConfigItem('db')['password'],
                [
                    \PDO::ATTR_PERSISTENT => true,
                ]
            );
        } catch (PDOException $e) {
            throw new PDOException($systemConfig->getConfigItem('db')['host'], (int)$e->getCode(), $e);
        }

        self::$pdoDriverInstance = $pdoDriver;

        return self::$pdoDriverInstance;
    }
}

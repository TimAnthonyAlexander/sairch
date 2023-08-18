<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\Config;

use TimAlexander\Sairch\model\Data\DataModel;

abstract class Config extends DataModel
{
    protected const file = '';

    private readonly string $file;

    public array $config;

    /**
     * @throws JsonException
     */
    public function __construct()
    {
        $this->file = __DIR__ . '/../../../' . static::file;
        $this->config = $this->readConfig();
    }

    /**
     * @throws JsonException
     */
    public function readConfig(): array
    {
        $config = [];
        if (file_exists($this->file)) {
            $config = json_decode(file_get_contents($this->file) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } else {
            $this->writeConfig($config);
        }
        return $config;
    }

    public function writeConfig(array $config): void
    {
        file_put_contents($this->file, json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function existsConfigItem(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    public function getConfigItem(string $item, mixed $defaultValue = null): mixed
    {
        if (!isset($this->config[$item])) {
            $this->config[$item] = $defaultValue;

            if ($defaultValue !== null) {
                $this->writeConfig($this->config);
            }

            return $defaultValue;
        }
        return $this->config[$item] ?? null;
    }

    public function setConfigItem(string $item, mixed $value): void
    {
        $this->config[$item] = $value;
        $this->writeConfig($this->config);
    }
}

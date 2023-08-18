<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\PrettyJson;

class PrettyJson
{
    public static function encode(array|object $data): string
    {
        $return = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return $return ?: '[]';
    }

    public static function decode(string $data): array
    {
        $return = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        foreach ($return as $key => $value) {
            if (is_string($value)) {
                $return[$key] = trim(str_replace([PHP_EOL, "\r", '\\', '\r'], '', $value));
                // Filter escape sequences
                $return[$key] = preg_replace('/\\\\u[0-9a-f]{4}/i', '', $return[$key]);
            }
        }
        return (array) $return;
    }
}

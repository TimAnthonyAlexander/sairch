<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\model\Data;

abstract class DataModel
{
    /**
     * This returns all protected properties of the inheriting class.
     */
    public function toArray(): array
    {
        $objectVars = get_object_vars($this);

        unset($objectVars['unset']);

        return self::turnIntoArrayRecursive($objectVars);
    }

    private static function turnIntoArrayRecursive(array $data): array
    {
        $array = [];
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $array[$key] = $value->toArray();
                continue;
            }
            if (is_array($value)) {
                $array[$key] = self::turnIntoArrayRecursive($value);
                continue;
            }
            $array[$key] = $value;
        }
        return $array;
    }
}

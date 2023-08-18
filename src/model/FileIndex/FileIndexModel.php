<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\model\FileIndex;

use TimAlexander\Sairch\model\Entity\EntityModel;

class FileIndexModel extends EntityModel
{
    public string $path = '';
    public string $name = '';
    public string $extension = '';
    public string $type = '';
    public int $size = 0;
    public string $modified = '';
    public string $accessed = '';
    public string $created = '';
    public string $updated;
    public string $tags = '';
    public string $notes = '';
    public string $metadata = '';

    public function set(
        array $data,
    ): void {
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }
    }
}

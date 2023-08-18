<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\Index;

use TimAlexander\Sairch\model\FileIndex\FileIndexModel;
use TimAlexander\Sairch\module\GetSystem\GetSystem;
use TimAlexander\Sairch\module\ProjectConfig\ProjectConfig;

ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '0');

error_reporting(E_ALL & ~E_WARNING);

class Index
{
    public readonly int $system;
    public readonly array $paths;
    public array $files = [];
    private readonly array $ignoreExtensions;

    public function __construct()
    {
        $getSystem = new GetSystem();
        $this->system = $getSystem->system;

        $projectConfig = new ProjectConfig();
        $this->paths = $projectConfig->getConfigItem('paths', $this->createDefaultPaths());

        $this->ignoreExtensions = $projectConfig->getConfigItem('ignoreExtensions', $this->createDefaultIgnoreExtensions());
    }

    private function createDefaultPaths(): array
    {
        return match ($this->system) {
            // 1= mac, 2= windows, 3= linux
            1 => [
                '~',
                '/Applications',
            ],
            2 => [
                'C:\\',
                'C:\\Users',
            ],
            3 => [
                '~',
                '/home',
            ],
        };
    }

    private function createDefaultIgnoreExtensions(): array
    {
        return [
            '7z',
            'avi',
            'ttf',
            'wav',
            'webm',
            'webp',
            'woff',
            'woff2',
            'xls',
            'xlsx',
            'xml',
            'zip',
            'dmg'
        ];
    }

    public function index(): void
    {
        foreach ($this->paths as $path) {
            if (str_starts_with($path, '~')) {
                $path = str_replace('~', $_SERVER['HOME'], $path);
            }
            $this->indexPath($path);
        }
    }

    private function indexPath(string $path): void
    {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;
            $fileType = filetype($filePath);
            $fileInfo = pathinfo($filePath);
            $fileSize = filesize($filePath);
            $fileExtension = $fileInfo['extension'] ?? '';

            if (in_array($fileExtension, $this->ignoreExtensions, true)) {
                continue;
            }

            try {
                $fileModified = filemtime($filePath);
                $fileAccessed = fileatime($filePath);
                $fileCreated = filectime($filePath);
            } catch (\Throwable) {
                $fileModified = 0;
                $fileAccessed = 0;
                $fileCreated = 0;
            }

            if (!is_readable($path . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }

            if (is_dir($filePath) || $fileType === 'dir') {
                $this->indexPath($filePath);
                $fileHash = '';
            } else {
                $fileHash = md5($filePath . $fileSize . $fileModified . $fileAccessed . $fileCreated);
            }

            if (FileIndexModel::existsById($fileHash)) {
                continue;
            }

            if ($fileHash === false) {
                continue;
            }

            $fileModified = date('c', $fileModified);
            $fileAccessed = date('c', $fileAccessed);
            $fileCreated = date('c', $fileCreated);

            $fileIndex = new FileIndexModel(
                $fileHash
            );

            $fileIndex->path = $filePath;
            $fileIndex->name = basename($filePath);
            $fileIndex->extension = $fileExtension;
            $fileIndex->type = $fileType;
            $fileIndex->size = $fileSize;
            $fileIndex->modified = $fileModified;
            $fileIndex->accessed = $fileAccessed;
            $fileIndex->created = $fileCreated;

            $fileIndex->save();

            $this->files[] = $fileIndex;
        }
    }
}

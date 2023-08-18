<?php

declare(strict_types=1);

namespace TimAlexander\Sairch\module\Index;

use TimAlexander\Sairch\model\FileIndex\FileIndexModel;
use TimAlexander\Sairch\module\GetSystem\GetSystem;
use TimAlexander\Sairch\module\ProjectConfig\ProjectConfig;

class Index
{
    public readonly int $system;
    public readonly array $paths;
    public array $files = [];

    public function __construct()
    {
        $getSystem = new GetSystem();
        $this->system = $getSystem->system;

        $projectConfig = new ProjectConfig();
        $this->paths = $projectConfig->getConfigItem('paths', $this->createDefaultPaths());
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
            $fileInfo = pathinfo($filePath);
            $fileType = filetype($filePath);
            $fileSize = filesize($filePath);
            $fileModified = filemtime($filePath);
            $fileAccessed = fileatime($filePath);
            $fileCreated = filectime($filePath);
            $fileHash = hash_file('sha256', $filePath);

            $fileModified = date('c', $fileModified);
            $fileAccessed = date('c', $fileAccessed);
            $fileCreated = date('c', $fileCreated);

            $fileIndex = new FileIndexModel(
                $fileHash
            );

            $fileIndex->path = $filePath;
            $fileIndex->name = $fileInfo['filename'];
            $fileIndex->extension = $fileInfo['extension'];
            $fileIndex->type = $fileType;
            $fileIndex->size = $fileSize;
            $fileIndex->modified = $fileModified;
            $fileIndex->accessed = $fileAccessed;
            $fileIndex->created = $fileCreated;

            $fileIndex->save();

            $this->files[] = $fileIndex;

            if ($fileType === 'dir') {
                $this->indexPath($filePath);
            }

            print "One file indexed.\n";
            die;
        }
    }
}

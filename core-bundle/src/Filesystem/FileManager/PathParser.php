<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Filesystem\FileManager;

use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemException;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;

class PathParser
{
    private FilesystemItem|null|false $item = false;
    private array|false $contentData = false;

    public function __construct(
        private readonly Request                    $request,
        private readonly VirtualFilesystemInterface $storage)
    {
    }

    public function getFileItem(): FilesystemItem|null
    {
        if (false === $this->item) {
            $this->item = $this->getItem($this->request->query->get('path'));
        }

        return $this->item?->isFile() ? $this->item : null;
    }

    public function getDirectoryItem(): FilesystemItem|null
    {
        if (false === $this->item) {
            $this->item = $this->getItem($this->request->query->get('path'));
        }

        return !$this->item?->isFile() ? $this->item : null;
    }

    /**
     * @return list<FilesystemItem>|null
     */
    public function getItems(): array|null
    {
        if (null === ($data = $this->getDecodedContent()) || !is_array($paths = $data['paths'] ?? null)) {
            return null;
        }

        $items = [];

        foreach ($paths as $path) {
            if(null === ($item = $this->getItem((string)$path))) {
                continue;
            }

            // Make sure there are no duplicates
            $items[$item->getPath()] = $item;
        }

        return array_values($items);
    }

    /**
     * @return array{0: list<FilesystemItem>, 1: FilesystemItem}|null
     */
    public function getMoveItemsAndTarget(): array|null {
        $itemsFrom = $this->getItems();
        $itemTo = $this->getMoveTarget();

        if(!$itemsFrom || null === $itemTo || $itemTo->isFile()) {
            return null;
        }

        $validateMovePaths = static function (array $itemsFrom, FilesystemItem $itemTo): bool {
            $sourcePaths = array_map(
                static fn(FilesystemItem $item): string => $item->getPath(),
                $itemsFrom
            );

            $sourceDirectories = array_unique(
                array_map(
                    static fn(string $path): string => Path::getDirectory($path),
                    $sourcePaths
                )
            );

            // All sources must be inside the same directory and the target
            // itself not part of it.
            return count($sourceDirectories) === 1 && !in_array($itemTo->getPath(), $sourcePaths);
        };

        return $validateMovePaths($itemsFrom, $itemTo) ? [$itemsFrom, $itemTo] : null;
    }

    private function getItem(string|null $path): FilesystemItem|null
    {
        if (null === $path || Path::isAbsolute($path)) {
            return null;
        }

        try {
            return $this->storage->get($path);
        } catch (VirtualFilesystemException) {
            return null;
        }
    }

    private function getMoveTarget(): FilesystemItem|null
    {
        if (null === ($data = $this->getDecodedContent()) ||
            !is_string($target = $data['target'] ?? null) ||
            null === ($item = $this->getItem($target)) ||
            $item->isFile()
        ) {
            return null;
        }

        return $item;
    }
    private function getDecodedContent(): array|null
    {
        if (false !== $this->contentData) {
            return $this->contentData;
        }

        $content = $this->request->getContent();

        try {
            $data = json_decode($content, true, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (!is_array($data)) {
            $data = null;
        }

        return $this->contentData = $data;
    }
}

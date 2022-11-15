<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller\Backend\FileManager;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\FilesystemItemIterator;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Turbo\TurboBundle;

class FileManager extends AbstractBackendController
{
    public function __construct(
        private readonly VirtualFilesystemInterface $storage,
        private readonly Studio                     $studio,
        private readonly array                      $validImageExtensions,
    )
    {
    }

    #[Route('/contao/file-manager', name: 'contao_file_manager', defaults: ['_scope' => 'backend'])]
    public function __invoke(): Response
    {
        return $this->render(
            '@Contao/backend/file_manager/layout.html.twig',
            [
                'title' => 'File Manager',
                'headline' => 'File Manager',
                'listing' => $this->getListingData(),
                'address' => $this->getAddressData(),
                'tree' => $this->getTreeData(),
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_navigate/{path}',
        name: '_contao_file_manager_navigate',
        requirements: ['path' => '.+'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _navigate(string $path, Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        $path = $this->getPathFromInput($path);

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/_navigate.stream.html.twig',
            [
                'listing' => $this->getListingData($path),
                'address' => $this->getAddressData($path),
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_details',
        name: '_contao_file_manager_details',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
    )]
    public function _details(Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        $detailsData = $this->getDetailsData($this->parsePaths($request));

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/_details.stream.html.twig',
            [
                'details' => $detailsData ?: null,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_delete',
        name: '_contao_file_manager_delete',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
    )]
    public function _delete(Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        $files = 0;
        $directories = 0;

        $paths = $this->parsePaths($request);

        if(!$paths) {
            return $this->renderNative('@Contao/backend/file_manager/stream/_error.stream.html.twig');
        }

        foreach ($paths as $path) {
            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
                $files++;
            } else {
                $this->storage->deleteDirectory($path);
                $directories++;
            }
        }

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/_delete.stream.html.twig',
            [
                'listing' => $this->getListingData(Path::getDirectory($paths[0])),
                'tree' => $directories ? $this->getTreeData() : null,
                'stats' => [
                    'deleted_files' => $files,
                    'deleted_directories' => $directories,
                ],
            ]
        );
    }

    private function getListingData(string $path = ''): array
    {
        $items = $this->storage->listContents($path);
        $items = $this->sortDefault($items);

        return array_map(
            fn(FilesystemItem $item): array => [
                'item' => $item,
                'preview' => $this->generatePreviewImage($item),
            ],
            $items
        );
    }

    private function getAddressData(string $path = ''): array
    {
        $paths = [];

        while (true) {
            $paths[$path] = Path::getFilenameWithoutExtension($path);

            if ($path === '') {
                break;
            }

            $path = Path::getDirectory($path);
        }

        return array_reverse($paths, true);
    }

    private function getTreeData(): array
    {
        $items = $this->storage
            ->listContents('', true)
            ->directories()
            ->sort();

        $prefixTree = [];

        foreach ($items as $item) {
            $parts = explode('/', $path = $item->getPath());
            $node = &$prefixTree;

            foreach ($parts as $part) {
                /** @phpstan-ignore-next-line */
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }

                $node = &$node[$part];
            }
        }

        return $prefixTree;
    }

    private function getDetailsData(array $paths): array|null
    {
        $items = array_filter(
            array_map(
                fn(string $path): FilesystemItem|null => $this->storage->get($path),
                $paths
            )
        );

        if (!$items) {
            return null;
        }

        $items = $this->sortDefault(new FilesystemItemIterator($items));

        return [
            'operations' => [
                'delete' => true,
                'rename' => true,
                'edit' => true,
            ],
            'items' => $items,
        ];
    }

    private function getPathFromInput(string $path): string
    {
        if (Path::isAbsolute($path)) {
            return '';
        }

        while (!$this->storage->directoryExists($path) && $path) {
            $path = Path::getDirectory($path);
        }

        return $path;
    }

    private function parsePaths(Request $request): array
    {
        try {
            $data = json_decode($request->getContent() ?: '', true, 3, JSON_THROW_ON_ERROR)['paths'] ?? null;
        } catch (\JsonException) {
            $data = null;
        }

        return is_array($data) ?
            array_filter($data, fn($path) => $this->storage->has((string)$path)) :
            [];
    }

    #[Route(
        '/contao/file-manager/_rename',
        name: '_contao_file_manager_rename',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
    )]
    public function _rename(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $this->storage->move($data['path'], $data['new']);

        $path = Path::getDirectory($data['new']);

        $items = $this->storage->listContents($path);
        $items = $this->sortDefault($items);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);


        return $this->renderNative(
            '@Contao/backend/file_manager/_rename.stream.html.twig',
            [
                'path' => $path,
                'parent_paths' => $this->getParentPaths($path),
                'items' => $items,
                'current' => $this->storage->get($path),
                'properties' => [
                    'items' => [
                        [
                            'item' => $item = $this->storage->get($data['new']),
                            'preview_image' => $this->generatePreviewImage($item),
                        ]
                    ]
                ],
            ],
        );
    }

    private function getParentPaths(string $path): array
    {
        if (!$path) {
            return [];
        }

        $paths = [];

        while ($path = Path::getDirectory($path)) {
            $paths[$path] = Path::getFilenameWithoutExtension($path);
        }

        return ['' => '', ...array_reverse($paths, true)];
    }

    private function sortDefault(FilesystemItemIterator $items): array
    {
        return [...$items->directories()->sort()->toArray(), ...$items->files()->sort()];
    }

    private function generatePreviewImage(FilesystemItem $item): Figure|null
    {
        if (!in_array($item->getExtension(true), $this->validImageExtensions, true)) {
            return null;
        }

        $figure = $this->studio
            ->createFigureBuilder()
            ->fromStorage($this->storage, $item->getPath())
            ->setSize([240, 180])
            ->buildIfResourceExists();

        return $figure;
    }
}

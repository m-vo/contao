<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller\Backend\FileManager;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\FilesystemItemIterator;
use Contao\CoreBundle\Filesystem\VirtualFilesystemException;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\StringUtil;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Turbo\TurboBundle;

class FileManager extends AbstractBackendController
{
    private array $editableFileTypes;
    private int $maxEditFileSize = 2 * 1024 * 1024; // 2MiB

    public function __construct(
        private readonly VirtualFilesystemInterface $storage,
        private readonly Studio                     $studio,
        private readonly array                      $validImageExtensions,
    )
    {
        $this->editableFileTypes = StringUtil::trimsplit(
            ',', strtolower($GLOBALS['TL_DCA']['tl_files']['config']['editableFileTypes'] ?? '')
        );
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

        $path = rtrim($path, '/');
        $item = $this->storage->get($path);

        if ($item?->isFile()) {
            return $this->renderNative(
                '@Contao/backend/file_manager/stream/viewer.stream.html.twig',
                [
                    'viewer' => $this->getViewerData($item),
                ]
            );
        }

        $path = $this->getDirectoryPathFromInput($path);

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/navigate.stream.html.twig',
            [
                'listing' => $this->getListingData($path),
                'address' => $this->getAddressData($path),
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_edit_source',
        name: '_contao_file_manager_edit_source',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
    )]
    public function _edit_source(Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        if (!is_string($path = $request->get('path')) ||
            null === ($item = $this->storage->get($path)) ||
            !$item->isFile()
        ) {
            return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        try {
            $this->storage->write($item->getPath(), $request->get('source', ''));
        } catch (VirtualFilesystemException $e) {
            return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                'message' => 'Could not write to file: ' . $e->getMessage(),
            ]);
        }

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/edit_source.stream.html.twig',
            [
                'message' => $this->getViewerData($item),
                'close_viewer' => $request->get('save_close') === '',
                'item' => $item,
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
            '@Contao/backend/file_manager/stream/details.stream.html.twig',
            [
                'details' => $detailsData ?: null,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_delete',
        name: '_contao_file_manager_delete',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'DELETE',
    )]
    public function _delete(Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        $files = 0;
        $directories = 0;

        $paths = $this->parsePaths($request);

        if (!$paths) {
            return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        foreach ($paths as $path) {
            if ($this->storage->fileExists($path)) {
                try {
                    $this->storage->delete($path);
                } catch (VirtualFilesystemException $e) {
                    return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not delete file: ' . $e->getMessage(),
                    ]);
                }
                $files++;
            } else {
                try {
                    $this->storage->deleteDirectory($path);
                } catch (VirtualFilesystemException $e) {
                    return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not delete directory: ' . $e->getMessage(),
                    ]);
                }
                $directories++;
            }
        }

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/delete.stream.html.twig',
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


    #[Route(
        '/contao/file-manager/_move',
        name: '_contao_file_manager_move',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'PATCH',
    )]
    public function _move(Request $request): Response
    {
        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        $files = 0;
        $directories = 0;

        [$pathsFrom, $pathTo] = $this->parseMovePaths($request);

        if ('' !== $pathTo && $this->storage->get($pathTo)?->isFile() !== false) {
            return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                'message' => 'Can only move items to an existing directory.',
            ]);
        }

        foreach ($pathsFrom as $index => $path) {
            if (null === ($item = $this->storage->get($path))) {
                unset($pathsFrom[$index]);

                continue;
            }

            if ($item->isFile()) {
                try {
                    $this->storage->move($item->getPath(), Path::join($pathTo, $item->getName()));
                } catch (VirtualFilesystemException $e) {
                    return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not move: ' . $e->getMessage(),
                    ]);
                }
                $files++;
            } else {
                return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                    'message' => 'Moving directories is not implemented, yet',
                ]);
                //$directories++;
            }
        }

        if ($directories + $files === 0) {
            return $this->renderNative('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        return $this->renderNative(
            '@Contao/backend/file_manager/stream/move.stream.html.twig',
            [
                'listing' => $this->getListingData(Path::getDirectory($pathsFrom[array_key_first($pathsFrom)] ?? '')),
                'tree' => $directories ? $this->getTreeData() : null,
                'stats' => [
                    'moved_files' => $files,
                    'moved_directories' => $directories,
                    'targetPath' => $pathTo,
                ],
            ]
        );
    }

    private function getListingData(string $path = ''): array
    {
        $items = $this->storage->listContents($path);
        $items = $this->sortDefault($items);

        $data = array_map(
            fn(FilesystemItem $item): array => [
                'item' => $item,
                'preview' => $this->generatePreviewImage($item),
            ],
            $items
        );

        return [
            'data' => $data,
            'parent_path' => $path ? Path::getDirectory($path) : null,
        ];
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

    private function getViewerData(FilesystemItem $item): array
    {
        $data = [
            'item' => $item,
            'edit' => false,
            'source' => null,
        ];

        $extension = $item->getExtension(true);

        if (in_array($extension, $this->editableFileTypes, true)) {
            $data['edit'] = true;

            if ($item->getFileSize() <= $this->maxEditFileSize) {
                $data['source'] = $this->storage->read($item->getPath());
            }
        }

        return $data;
    }

    private function getDirectoryPathFromInput(string $path): string
    {
        $path = rtrim($path, '/');

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
            return [];
        }

        return is_array($data) ?
            array_filter($data, fn($path) => $this->storage->has((string)$path)) :
            [];
    }

    private function parseMovePaths(Request $request): array
    {
        try {
            $data = json_decode($request->getContent() ?: '', true, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [[], null];
        }

        $from = is_array($fromRaw = ($data['from'] ?? null)) ?
            array_filter($fromRaw, fn($path) => $this->storage->has((string)$path)) :
            [];

        $to = is_string($toRaw = ($data['to'] ?? null)) && $this->storage->has($toRaw) ?
            $toRaw :
            '';

        return [$from, $to];
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
            '@Contao/backend/file_manager/rename.stream.html.twig',
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

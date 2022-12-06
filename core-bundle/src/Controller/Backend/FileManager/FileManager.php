<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Controller\Backend\FileManager;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Filesystem\FileManager\PathParser;
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
    private int $maxEditFileSize;

    public function __construct(
        private readonly VirtualFilesystemInterface $storage,
        private readonly Studio                     $studio,
        private readonly array                      $validImageExtensions,
    )
    {
        $this->editableFileTypes = StringUtil::trimsplit(',', strtolower($GLOBALS['TL_DCA']['tl_files']['config']['editableFileTypes'] ?? ''));
        $this->maxEditFileSize = 2 * 1024 * 1024; // 2MiB;
    }

    #[Route('/contao/file-manager', name: 'contao_file_manager', defaults: ['_scope' => 'backend'])]
    public function __invoke(): Response
    {
        return $this->render(
            '@Contao/backend/file_manager/layout.html.twig',
            [
                'title' => 'File Manager',
                'headline' => 'File Manager',
                'listing' => $this->compileListingData(),
                'address' => $this->compileAddressData(),
                'tree' => $this->getTreeData(),
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_navigate',
        name: '_contao_file_manager_navigate',
        requirements: ['path' => '.*'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _navigate(Request $request): Response
    {
        $parser = new PathParser($request, $this->storage);

        if (null !== ($file = $parser->getFileItem())) {
            return $this->turboStream(
                '@Contao/backend/file_manager/stream/viewer.stream.html.twig',
                [
                    'viewer' => $this->compileViewerData($file),
                ]
            );
        }

        $directoryPath = $parser->getDirectoryItem()?->getPath() ?? '';

        return $this->turboStream(
            '@Contao/backend/file_manager/stream/navigate.stream.html.twig',
            [
                'listing' => $this->compileListingData($directoryPath),
                'address' => $this->compileAddressData($directoryPath),
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
        $parser = new PathParser($request, $this->storage);

        if (null === ($item = $parser->getFileItem()) || null === ($source = $request->get('source'))) {
            return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        try {
            $this->storage->write($item->getPath(), $source);
        } catch (VirtualFilesystemException $e) {
            return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                'message' => 'Could not write to file: ' . $e->getMessage(),
            ]);
        }

        return $this->turboStream(
            '@Contao/backend/file_manager/stream/edit_source.stream.html.twig',
            [
                'message' => $this->compileViewerData($item),
                'close_viewer' => '' === $request->get('save_close'),
                'item' => $item,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_details',
        name: '_contao_file_manager_details',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
        condition: "request.headers.get('Accept') === 'text/vnd.turbo-stream.html'"
    )]
    public function _details(Request $request): Response
    {
        $parser = new PathParser($request, $this->storage);
        $items = $parser->getItems();

        return $this->turboStream(
            '@Contao/backend/file_manager/stream/details.stream.html.twig',
            [
                'details' => $items ? $this->compileDetailsData($items) : null,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_delete',
        name: '_contao_file_manager_delete',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'DELETE',
        condition: "request.headers.get('Accept') === 'text/vnd.turbo-stream.html'"
    )]
    public function _delete(Request $request): Response
    {
        $parser = new PathParser($request, $this->storage);
        $items = $parser->getItems();

        if (!$items) {
            return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        $stats = [
            'deleted_files' => 0,
            'deleted_directories' => 0,
        ];

        foreach ($items as $item) {
            if ($item->isFile()) {
                try {
                    $this->storage->delete($item->getPath());
                } catch (VirtualFilesystemException $e) {
                    return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not delete file: ' . $e->getMessage(),
                    ]);
                }
                ++$stats['deleted_files'];
            } else {
                try {
                    $this->storage->deleteDirectory($item->getPath());
                } catch (VirtualFilesystemException $e) {
                    return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not delete directory: ' . $e->getMessage(),
                    ]);
                }
                ++$stats['deleted_directories'];
            }
        }

        return $this->turboStream(
            '@Contao/backend/file_manager/stream/delete.stream.html.twig',
            [
                'listing' => $this->compileListingData(Path::getDirectory($items[0]->getPath())),
                'tree' => $stats['deleted_directories'] ? $this->getTreeData() : null,
                'stats' => $stats,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_move',
        name: '_contao_file_manager_move',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'PATCH',
        condition: "request.headers.get('Accept') === 'text/vnd.turbo-stream.html'"
    )]
    public function _move(Request $request): Response
    {
        $parser = new PathParser($request, $this->storage);

        if (![$itemsFrom, $itemTo] = $parser->getMoveItemsAndTarget()) {
            return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                'message' => 'Cannot move. Invalid source/targets.',
            ]);
        }

        $stats = [
            'moved_files' => 0,
            'moved_directories' => 0,
            'targetPath' => $itemTo->getPath(),
        ];

        foreach ($itemsFrom as $itemFrom) {
            if ($itemFrom->isFile()) {
                try {
                    $this->storage->move($itemFrom->getPath(), Path::join($itemTo->getPath(), $itemFrom->getName()));
                } catch (VirtualFilesystemException $e) {
                    return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                        'message' => 'Could not move: ' . $e->getMessage(),
                    ]);
                }
                ++$stats['moved_files'];
            } else {
                return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig', [
                    'message' => 'Moving directories is not implemented, yet',
                ]);
                //++$stats['moved_directories'];
            }
        }

        if (0 === $stats['moved_files'] + $stats['moved_directories']) {
            return $this->turboStream('@Contao/backend/file_manager/stream/error.stream.html.twig');
        }

        return $this->turboStream(
            '@Contao/backend/file_manager/stream/move.stream.html.twig',
            [
                'listing' => $this->compileListingData(Path::getDirectory($itemsFrom[0]->getPath())),
                'tree' => $stats['moved_directories'] ? $this->getTreeData() : null,
                'stats' => $stats,
            ]
        );
    }

    private function compileListingData(string $path = ''): array
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
            'current_path' => $path,
            'parent_path' => $path ? Path::getDirectory($path) : null,
        ];
    }

    private function compileAddressData(string $path = ''): array
    {
        $paths = [];

        while (true) {
            $paths[$path] = Path::getFilenameWithoutExtension($path);

            if ('' === $path) {
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

    /**
     * @param list<FilesystemItem> $items
     */
    private function compileDetailsData(array $items): array|null
    {
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

    private function compileViewerData(FilesystemItem $item): array
    {
        $data = [
            'item' => $item,
            'edit' => false,
            'source' => null,
        ];

        $extension = $item->getExtension(true);

        if (\in_array($extension, $this->editableFileTypes, true)) {
            $data['edit'] = true;

            if ($item->getFileSize() <= $this->maxEditFileSize) {
                $data['source'] = $this->storage->read($item->getPath());
            }
        }

        return $data;
    }

    /*
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
                        ],
                    ],
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
    */

    private function sortDefault(FilesystemItemIterator $items): array
    {
        return [...$items->directories()->sort()->toArray(), ...$items->files()->sort()];
    }

    private function generatePreviewImage(FilesystemItem $item): Figure|null
    {
        if (!\in_array($item->getExtension(true), $this->validImageExtensions, true)) {
            return null;
        }

        return $this->studio
            ->createFigureBuilder()
            ->fromStorage($this->storage, $item->getPath())
            ->setSize([240, 180])
            ->buildIfResourceExists();
    }
}

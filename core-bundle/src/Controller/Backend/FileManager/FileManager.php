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
        $context = [
            'headline' => 'File Manager'
        ];

        return $this->render('@Contao/backend/file_manager/index.html.twig', $context);
    }

    #[Route(
        '/contao/file-manager/_content/{path}',
        name: '_contao_file_manager_content',
        requirements: ['path' => '.+'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _content(string $path, Request $request): Response
    {
        while (!$this->storage->directoryExists($path) && $path) {
            $path = Path::getDirectory($path);
        }

        $items = $this->storage->listContents($path, false);
        $items = $this->sortDefault($items);

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderNative(
            '@Contao/backend/file_manager/_content.stream.html.twig',
            [
                'path' => $path,
                'parent_paths' => $this->getParentPaths($path),
                'items' => $items,
                'current' => $this->storage->get($path),
            ],
        );
    }

    #[Route(
        '/contao/file-manager/_tree',
        name: '_contao_file_manager_tree',
        defaults: ['_scope' => 'backend']
    )]
    public function _tree(): Response
    {
        $items = $this->storage
            ->listContents('', true, VirtualFilesystemInterface::FORCE_SYNC)
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

        return $this->renderNative(
            '@Contao/backend/file_manager/_tree.html.twig',
            [
                'tree' => $prefixTree,
            ]
        );
    }

    #[Route(
        '/contao/file-manager/_properties',
        name: '_contao_file_manager_properties',
        defaults: ['_scope' => 'backend', '_token_check' => false],
        methods: 'POST',
    )]
    public function _properties(Request $request): Response
    {
        $items = [];

        if (($content = $request->getContent()) && ($data = json_decode($content, true))) {
            foreach ($data['paths'] ?? [] as $path) {
                if (is_string($path)) {
                    $items[] = $this->storage->get(rtrim($path, '/'), VirtualFilesystemInterface::FORCE_SYNC);
                }
            }
        }

        $items = $this->sortDefault(new FilesystemItemIterator(array_filter($items)));

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderNative(
            '@Contao/backend/file_manager/_properties.stream.html.twig',
            [
                'items' => array_map(
                    fn(FilesystemItem $item): array => [
                        'item' => $item,
                        'preview_image' => $this->generatePreviewImage($item),
                    ],
                    $items
                ),
            ],
        );
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
            ->buildIfResourceExists()
        ;

        return $figure;
    }
}

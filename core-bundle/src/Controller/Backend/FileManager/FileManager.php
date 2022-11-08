<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller\Backend\FileManager;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Filesystem\FilesystemItem;
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
        private readonly Studio $studio,
        private readonly array $validImageExtensions,
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
        requirements: ['path'=> '.+'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _content(string $path, Request $request): Response
    {
        while(!$this->storage->directoryExists($path) && $path) {
            $path = Path::getDirectory($path);
        }

        $items = $this->storage->listContents($path, false);
        $items = [...$items->directories()->sort()->toArray(), ...$items->files()->sort()];

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
            ->sort()
        ;

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
        '/contao/file-manager/_select/{path}',
        name: '_contao_file_manager_select',
        requirements: ['path'=> '.+'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _select(string $path, Request $request): Response
    {
        $item = $path ?
            $this->storage->get(rtrim($path, '/'), VirtualFilesystemInterface::FORCE_SYNC) :
            null
        ;

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return $this->renderNative(
            '@Contao/backend/file_manager/_select.stream.html.twig',
            [
                'current' => $item,
                'preview_image' => $this->generatePreviewImage($item),
            ],
        );
    }

    private function getParentPaths(string $path): array
    {
        if(!$path) {
            return [];
        }

        $paths = [];

        while($path = Path::getDirectory($path)) {
            $paths[$path] = Path::getFilenameWithoutExtension($path);
        }

        return ['' => '', ...array_reverse($paths, true)];
    }

    private function generatePreviewImage(FilesystemItem|null $item): Figure|null
    {
        if(null === $item) {
            return null;
        }

        if(!in_array($item->getExtension(true), $this->validImageExtensions, true)) {
            return null;
        }

        return $this->studio
            ->createFigureBuilder()
            ->fromStorage($this->storage, $item->getPath())
            ->setSize([240, 180])
            ->buildIfResourceExists()
        ;
    }
}

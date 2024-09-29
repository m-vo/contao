<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionProviderInterface;
use Contao\CoreBundle\Twig\Studio\TemplateSkeletonFactory;

class CoreActionProvider implements ActionProviderInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage,
        private readonly FinderFactory              $finderFactory,
        private readonly TemplateSkeletonFactory    $templateSkeletonFactory,
    )
    {
    }

    public function getActions(): array
    {
        $actions = [
            new SaveCustomTemplateAction($this->filesystemLoader, $this->customTemplatesStorage),
            new DeleteCustomTemplateAction($this->filesystemLoader, $this->customTemplatesStorage),
            new CreateCustomTemplateAction($this->filesystemLoader, $this->customTemplatesStorage, $this->templateSkeletonFactory),
        ];

        foreach (['content_element', 'frontend_module'] as $prefix) {
            $actions = [
                ...$actions,
                new CreateVariantTemplateAction($this->filesystemLoader, $this->customTemplatesStorage, $this->templateSkeletonFactory, $prefix),
                new RenameVariantTemplateAction($this->filesystemLoader, $this->customTemplatesStorage, $this->finderFactory, $prefix),
            ];
        }

        return $actions;
    }
}

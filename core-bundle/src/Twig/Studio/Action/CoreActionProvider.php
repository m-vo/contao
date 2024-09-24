<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionProviderInterface;

class CoreActionProvider implements ActionProviderInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage,
    )
    {
    }

    public function getActions(): array
    {
        $actions = [
            new UpdateTemplateAction($this->filesystemLoader, $this->customTemplatesStorage),
            new DeleteTemplateAction($this->filesystemLoader, $this->customTemplatesStorage),
            new CreateCustomTemplateAction($this->filesystemLoader, $this->customTemplatesStorage),
        ];

        foreach (['content_element', 'frontend_module'] as $prefix) {
            $actions = [
                ...$actions,
                new CreateVariantTemplateAction($this->filesystemLoader, $this->customTemplatesStorage, $prefix),
                new RenameVariantTemplateAction($this->filesystemLoader, $this->customTemplatesStorage, $prefix),
            ];
        }

        return $actions;
    }
}

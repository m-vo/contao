<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionResult;
use Contao\CoreBundle\Twig\Studio\TemplateSkeleton;
use Symfony\Component\Filesystem\Path;

class CreateCustomTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage
    )
    {
    }

    public function getName(): string
    {
        return 'create_custom_template';
    }

    public function canExecute(ActionContext $context): bool
    {
        $identifier = $context->getParameter('identifier');

        // Check if the first template in the chain is not already a custom
        // template from the Contao_Global namespace.
        return (ContaoTwigUtil::parseContaoName($this->filesystemLoader->getFirst($identifier))[0] ?? '') !== 'Contao_Global';
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');

        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));
        $filename = Path::join('content_element', "$identifier.$extension");

        // Create a new template skeleton for the custom template
        $templateSkeleton = new TemplateSkeleton();

        $this->customTemplatesStorage->write($filename, $templateSkeleton->getContent());

        // Reset and prime filesystem loader // todo check if this is needed
        $this->filesystemLoader->reset();
        $this->filesystemLoader->exists("@Contao/$filename");

        return ActionResult::success('A custom template was created.');
    }
}

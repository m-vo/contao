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

class UpdateTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage
    )
    {
    }

    public function getName(): string
    {
        return 'update_custom_template';
    }

    public function canExecute(ActionContext $context): bool
    {
        $identifier = $context->getParameter('identifier');

        // Check if the first template in the chain is a custom
        // template from the Contao_Global namespace.
        return (ContaoTwigUtil::parseContaoName($this->filesystemLoader->getFirst($identifier))[0] ?? '') === 'Contao_Global';
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');

        // todo
        return ActionResult::success('The template was saved.');
    }
}

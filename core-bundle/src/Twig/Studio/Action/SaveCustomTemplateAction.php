<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionResult;

class SaveCustomTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage
    )
    {
    }

    public function getName(): string
    {
        return 'save_custom_template';
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

        // Get the file that an editor is allowed to edit
        $first = $this->filesystemLoader->getFirst($identifier);

        if ((ContaoTwigUtil::parseContaoName($first)[0] ?? '') !== 'Contao_Global') {
            throw new \InvalidArgumentException(sprintf('There is no user template for identifier "%s".', $identifier));
        }

        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));
        $this->customTemplatesStorage->write("$identifier.$extension", $context->getParameter('request')->getContent());

        return ActionResult::streamStep(
            '@Contao/backend/template_studio/editor/action/save_custom_template.stream.html.twig',
            [
                'identifier' => $identifier,
            ]
        );
    }
}

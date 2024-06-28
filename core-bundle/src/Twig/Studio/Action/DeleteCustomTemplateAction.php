<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionResult;

class DeleteCustomTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage
    )
    {
    }

    public function getName(): string
    {
        return 'delete_custom_template';
    }

    public function canExecute(ActionContext $context): bool
    {
        // todo: what about original templates (e.g. for own content elements/modules)?
        $identifier = $context->getParameter('identifier');

        // Check if the first template in the chain is a custom
        // template from the Contao_Global namespace.
        return (ContaoTwigUtil::parseContaoName($this->filesystemLoader->getFirst($identifier))[0] ?? '') === 'Contao_Global';
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');

        // Show dialog to confirm deletion
        if($context->getParameter('request')->get('confirm_delete') === null) {
            return ActionResult::streamStep(
                '@Contao/backend/template_studio/editor/action/confirm_delete_custom_template.stream.html.twig',
                [
                    'identifier' => $identifier,
                    'action' => $this->getName(),
                ]
            );
        }

        // Delete the template
        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));
        $isLast = \count($this->filesystemLoader->getInheritanceChains()[$identifier]) === 1;

        $this->customTemplatesStorage->delete("$identifier.$extension");

        // Delete directory?

        // Refresh things
        // todo

        return ActionResult::streamStep(
            '@Contao/backend/template_studio/editor/action/delete_custom_template.stream.html.twig',
            [
                'identifier' => $identifier,
                'close_tab' => $isLast,
            ]
        );
    }
}

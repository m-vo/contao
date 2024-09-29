<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionResult;
use Contao\CoreBundle\Twig\Studio\TemplateSkeletonFactory;
use Symfony\Component\Filesystem\Path;

class CreateVariantTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage,
        private readonly TemplateSkeletonFactory $templateSkeletonFactory,
        private readonly string $prefix
    )
    {
    }

    public function getName(): string
    {
        return 'create_' . $this->prefix . '_variant_template';
    }

    public function canExecute(ActionContext $context): bool
    {
        $identifier = $context->getParameter('identifier');

        return preg_match('%^' . preg_quote($this->prefix, '%') . '/[^_/][^/]*$%', $identifier) === 1;
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');
        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));

        $getUniqueName = function() use ($identifier, $extension) {
            $newNameSuffix = 'new_variant';
            $newName = "$identifier/$newNameSuffix.$extension";

            $index = 2;
            while($this->filesystemLoader->exists("@Contao/$newName")) {
                $newName = "$identifier/$newNameSuffix$index.$extension";
                $index++;
            }

            return $newName;
        };

        // Create a new template skeleton for the variant
        $content = $this->templateSkeletonFactory->create()->getContent("@Contao/$identifier.$extension");

        if (!$this->customTemplatesStorage->directoryExists($identifier)) {
            $this->customTemplatesStorage->createDirectory($identifier);
        }

        $this->customTemplatesStorage->write($getUniqueName(), $content);

        // Reset and prime filesystem loader // todo check if this is needed

        return ActionResult::success('A new variant was created.');
    }
}

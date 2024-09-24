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

class CreateVariantTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage,
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

        return preg_match('%^' . preg_quote($this->prefix, '%') . '/[^/]+$%', $identifier) === 1;
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');

        if ($context->…) {
            // Show textbox where the user can enter the name of the template
            $invalidNames = [];
            $pattern = sprintf('^(?!(%s)$).*', implode('|', array_map(preg_quote(...), $invalidNames)));

            return ActionResult::streamStep('@Contao/backend/template_studio/action/rename.stream.html.twig', $context);
        }

        $name = $context->getParameter('name');

        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));

        $directory = Path::join($this->prefix, $identifier);
        $filename = Path::join($directory, "$name.$extension");

        if ($this->customTemplatesStorage->fileExists($filename)) {
            return ActionResult::error('The given name already exists.');
        }

        // Create a new template skeleton for the variant
        $templateSkeleton = new TemplateSkeleton();

        if (!$this->customTemplatesStorage->directoryExists($directory)) {
            $this->customTemplatesStorage->createDirectory($directory);
        }

        $this->customTemplatesStorage->write($filename, $templateSkeleton->getContent());

        // Reset and prime filesystem loader // todo check if this is needed
        $this->filesystemLoader->reset();
        $this->filesystemLoader->exists("@Contao/$filename");

        return ActionResult::success('The new variant was created.');
    }
}

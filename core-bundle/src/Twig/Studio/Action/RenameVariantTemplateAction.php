<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Action;

use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionResult;

class RenameVariantTemplateAction implements ActionInterface
{
    public function __construct(
        private readonly ContaoFilesystemLoader     $filesystemLoader,
        private readonly VirtualFilesystemInterface $customTemplatesStorage,
        private readonly FinderFactory              $finderFactory,
        private readonly string                     $prefix
    )
    {
    }

    public function getName(): string
    {
        return 'rename_' . $this->prefix . '_variant_template';
    }

    public function canExecute(ActionContext $context): bool
    {
        $identifier = $context->getParameter('identifier');

        return preg_match('%^' . preg_quote($this->prefix, '%') . '/[^/]+/.+$%', $identifier) === 1;
    }

    public function execute(ActionContext $context): ActionResult
    {
        $identifier = $context->getParameter('identifier');
        $extension = ContaoTwigUtil::getExtension($this->filesystemLoader->getFirst($identifier));

        preg_match('%^(' . preg_quote($this->prefix, '%') . '/[^/]+)/.+$%', $identifier, $matches);
        $baseTemplateIdentifier = $matches[1];

        // Show dialog to select a name
        if (($newNameSuffix = $context->getParameter('request')->get('new_name')) === null) {
            $existingVariants = array_diff(
                array_keys(
                    iterator_to_array(
                        $this->finderFactory->create()
                            ->identifier($baseTemplateIdentifier)
                            ->withVariants()
                    )
                ),
                [$baseTemplateIdentifier]
            );

            $getName = static fn(string $identifier): string => substr($identifier, strlen($baseTemplateIdentifier) + 1);

            // Disallow selecting a name of an existing variant
            $pattern = sprintf('^(?!(%s)$).*', implode('|', array_map(preg_quote(...), array_map($getName(...), $existingVariants))));

            return ActionResult::streamStep(
                '@Contao/backend/template_studio/editor/action/rename_variant_template.stream.html.twig',
                [
                    'identifier' => $identifier,
                    'action' => $this->getName(),
                    'base_identifier' => $baseTemplateIdentifier,
                    'current_name' => $getName($identifier),
                    'extension' => $extension,
                    'pattern' => $pattern,
                ]
            );
        }

        // Rename template
        $oldName = "$identifier.$extension";
        $newName = "$baseTemplateIdentifier/$newNameSuffix.$extension";

        if ($this->customTemplatesStorage->fileExists($newName)) {
            return ActionResult::error('The given name already exists.');
        }

        // todo: recursively create directories if necessary

        $this->customTemplatesStorage->move($oldName, $newName);

        // Reset and prime filesystem loader // todo check if this is needed

        return ActionResult::success('The variant template was renamed.');
    }
}

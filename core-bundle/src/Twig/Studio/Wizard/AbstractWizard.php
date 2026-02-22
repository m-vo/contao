<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Wizard;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use Contao\CoreBundle\Twig\Studio\CacheInvalidator;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;

/**
 * @experimental
 */
abstract class AbstractWizard extends AbstractController implements WizardInterface
{
    private string|null $name = null;

    /**
     * @internal
     */
    #[Required]
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return "template_studio.wizard.{$this->getName()}.0";
    }

    public function getDescription(): string
    {
        return "template_studio.wizard.{$this->getName()}.1";
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['twig'] = Environment::class;
        $services['contao.filesystem.virtual.user_templates'] = VirtualFilesystemInterface::class;
        $services['contao.twig.studio.cache_invalidator'] = CacheInvalidator::class;

        return $services;
    }

    protected function update(WizardContext $context, string $code): void
    {
        // Update file
        $this->container
            ->get('contao.filesystem.virtual.user_templates')
            ->write($context->getUserTemplatesStoragePath(), $code)
        ;

        // Invalidate template cache of just the updated file
        $this->container
            ->get('twig')
            ->removeCache(
                $this->container
                    ->get('contao.twig.filesystem_loader')
                    ->getFirst($context->getIdentifier(), $context->getThemeSlug()),
            )
        ;
    }
}

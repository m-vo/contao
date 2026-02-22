<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Wizard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental
 */
class AddScriptToPageWizard extends AbstractWizard
{
    public function canExecute(WizardContext $context): bool
    {
        return 1 === preg_match('%^page/layout(?:$|/)%', $context->getIdentifier());
    }

    public function execute(Request $request, WizardContext $context): Response|null
    {
        // TODO: Implement execute() method.
    }
}

<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio\Wizard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental
 */
interface WizardInterface
{
    public function getLabel(): string;

    public function getDescription(): string;

    /**
     * Return true if the wizard can be executed in the given context.
     */
    public function canExecute(WizardContext $context): bool;

    /**
     * Execute the wizard and return a response that will be sent to the browser or
     * null if a default (Turbo stream) response should be generated instead.
     */
    public function execute(Request $request, WizardContext $context): Response|null;
}

<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

// todo: move to generic place
interface ActionInterface
{
    /**
     * Returns the unique name of the action.
     */
    public function getName(): string;

    /**
     * Returns true if the action can be executed in the given context by a
     * user with sufficient privileges.
     */
    public function canExecute(ActionContext $context): bool;

    /**
     * Executes the action and returns an action result.
     */
    public function execute(ActionContext $context): ActionResult;
}

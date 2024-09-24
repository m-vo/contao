<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

interface ActionProviderInterface
{
    /**
     * @return list<ActionInterface>
     */
    public function getActions(): array;
}

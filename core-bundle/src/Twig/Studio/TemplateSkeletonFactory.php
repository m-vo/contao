<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

use Contao\CoreBundle\Twig\Inspector\Inspector;
use Twig\Environment;

class TemplateSkeletonFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Inspector $inspector,
    )
    {
    }

    public function create(): TemplateSkeleton
    {
        return new TemplateSkeleton($this->twig, $this->inspector);
    }
}

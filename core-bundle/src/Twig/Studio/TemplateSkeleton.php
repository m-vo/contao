<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

use Contao\CoreBundle\Twig\Inspector\Inspector;
use Twig\Environment;

class TemplateSkeleton
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Inspector $inspector,
    )
    {
    }

    public function getContent(string $baseTemplate): string
    {
        $info = $this->inspector->inspectTemplate($baseTemplate);

        return $this->twig->render(
            "@Contao/backend/template_studio/editor/template_skeleton.twig.twig",
            [
                'type' => str_starts_with($baseTemplate, '@Contao/component') ? 'use' : 'extends',
                'template' => $info,
                'base_template' => $baseTemplate,
            ]
        );
    }
}

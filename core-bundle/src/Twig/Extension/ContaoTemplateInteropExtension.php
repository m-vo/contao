<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Twig\Extension;

use Contao\CoreBundle\Twig\Interop\ContaoTemplateVisitor;
use Contao\CoreBundle\Twig\Interop\DisplayProxyNode;
use Contao\CoreBundle\Twig\Interop\PartialTemplate;
use Twig\Extension\AbstractExtension;
use Webmozart\PathUtil\Path;

class ContaoTemplateInteropExtension extends AbstractExtension
{
    public function getNodeVisitors(): array
    {
        // fixme: add registry + ns handling
        $templateCandidates = [
            'mod_article.html.twig' => 'mod_article.html5',
        ];

        return [
            new ContaoTemplateVisitor(self::class, $templateCandidates),
        ];
    }

    /**
     * Templates will call this method when displaying legacy template content.
     *
     * by @see DisplayProxyNode.
     */
    public function render(string $name, array $blocks, array $context): string
    {
        $renderedBlocks = $this->renderBlocks($blocks, $context);

        $template = new PartialTemplate(
            Path::getFilenameWithoutExtension($name),
            $renderedBlocks,
            $context
        );

        return $template->parse();
    }

    private function renderBlocks(array $blocks, array $context): array
    {
        $rendered = [];

        foreach ($blocks as $name => $block) {
            ob_start();

            // Display block
            $block($context);

            $rendered[$name] = ob_get_clean();
        }

        return $rendered;
    }
}

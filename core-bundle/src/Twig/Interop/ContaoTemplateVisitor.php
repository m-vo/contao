<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Twig\Interop;

use Twig\Environment;
use Twig\Node\BlockNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\NodeVisitor\AbstractNodeVisitor;

/**
 * Handles rendering legacy Contao templates by injecting a display proxy.
 *
 * @internal
 */
class ContaoTemplateVisitor extends AbstractNodeVisitor
{
    /**
     * @var string
     */
    private $extensionName;

    /**
     * @var array<string, string>
     */
    private $templateCandidates;

    /**
     * @var array<string, array<string>>
     */
    private $blockNames = [];

    public function __construct(string $extensionName, array $templateCandidates)
    {
        $this->extensionName = $extensionName;
        $this->templateCandidates = $templateCandidates;
    }

    public function getPriority(): int
    {
        return 0;
    }

    protected function doEnterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof ModuleNode || null === ($template = $this->templateCandidates[$node->getTemplateName()] ?? null)) {
            return $node;
        }

        // Register used block names
        $this->blockNames[$template] = array_keys(iterator_to_array($node->getNode('blocks')));

        return $node;
    }

    protected function doLeaveNode(Node $node, Environment $env): ?Node
    {
        if (!$node instanceof ModuleNode || null === ($blocks = $this->blockNames[$node->getTemplateName()] ?? null)) {
            return $node;
        }

        // Create placeholder content for parent blocks that will be
        // substituted when Contao compiles the template
        $blockNodes = [];

        foreach ($blocks as $block) {
            $blockNodes[$block] = new BlockNode(
                $block, new TextNode('[[TL_PARENT]]', 0), 0
            );
        }

        $node->setNode('blocks', new Node($blockNodes));

        // Instead of outputting the PHP template body, we'll install a display
        // proxy that delegates the rendering to Contao's legacy template logic
        $displayProxy = new DisplayProxyNode($this->extensionName);

        $node->setNode('body', new Node([$displayProxy]));

        return $node;
    }
}

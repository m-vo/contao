<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\HtmlAttributes;

use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\HtmlAttributes\AttrsNode;
use Contao\CoreBundle\Twig\Slots\SlotNode;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\PrintNode;

class AttrsNodeTest extends TestCase
{
    public function testCompilesCode(): void
    {
        $compiler = new Compiler($this->createMock(Environment::class));

        $node = new AttrsNode(
            'foo',
            [],
            0
        );

        $node->compile($compiler);

        $expectedSource = <<<'SOURCE'
            $context['_slot_name'] = "foo";
            if ('' !== (string)($context['_slots']['foo'] ?? '')) {
                yield "foo";
            } else {
                yield "bar";
            }
            unset($context['_slot_name']);

            SOURCE;

        $this->assertSame($expectedSource, $compiler->getSource());
    }
}

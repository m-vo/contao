<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\HtmlAttributes;

use Contao\CoreBundle\Tests\Twig\HtmlAttributes\AttrsNodeTest;
use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

class AttrsNode extends Node implements NodeOutputInterface
{
    public function __construct(string $name, array $methodCalls, int $lineno)
    {
        parent::__construct([], ['name' => $name, 'methodCalls' => $methodCalls], $lineno);
    }

    public function compile(Compiler $compiler)
    {
        $name = $this->getAttribute('name');
        $methodCalls = $this->getAttribute('methodCalls');

        $context['foo_attributes'] = (new \Contao\CoreBundle\String\HtmlAttributes())
            ->mergeWith($context['foo_attributes'] ?? '')
        ;

        /** @see AttrsNodeTest::testCompilesCode() */
        $compiler
            ->addDebugInfo($this)
            ->write('$context[\'' . $name . '\'] = (new \Contao\CoreBundle\String\HtmlAttributes())')
            ->indent()
        ;

        foreach ($methodCalls as [$method, $arguments]) {
            $compiler->write('->' . $method)
        }

        $compiler
            ->write('->mergeWith($context[\'' . $name . '\'] ?? \'\'')
            ->outdent()
            ->write(";\n")
        ;



            ->string($name)
            ->raw(";\n")
            ->write('if (\'\' !== (string)($context[\'_slots\'][\'')
            ->raw($name)
            ->raw("'] ?? '')) {\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
        ;
    }
}

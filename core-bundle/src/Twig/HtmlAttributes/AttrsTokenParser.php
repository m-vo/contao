<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\HtmlAttributes;

use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

class AttrsTokenParser extends AbstractTokenParser
{
    /**
     * {% attrs <name> [<methodCall>]* %}
     *
     * Example using the "attrs()" function:
     *   {% set foo_attributes = attrs().addClass('bar').setIfExists('data-value', value|default).mergeWith(foo_attributes|default) %}
     *   <div{{ foo_attributes}}></div>
     *
     * Same example using the "{% attrs %}" tag:
     *   {% attrs foo_attributes addClass('bar') setIfExists('data-value', value) %}
     *   <div{{ foo_attributes}}></div>
     */
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();

        $name = $stream->expect(Token::NAME_TYPE)->getValue();
        $methodCalls = [];

        while(!$stream->nextIf(Token::BLOCK_END_TYPE)) {
            $methodCalls[] = $this->parseMethodCall($stream);
        }

        return new AttrsNode($name, $methodCalls, $token->getLine());
    }

    /**
     * @return array{0: string, 1: list<AbstractExpression>}
     */
    private function parseMethodCall(TokenStream $stream): array
    {
        $method = $stream->expect(Token::NAME_TYPE)->getValue();
        $stream->expect(Token::OPERATOR_TYPE, '(');
        $arguments = $this->parseArguments($stream);
        $stream->expect(Token::PUNCTUATION_TYPE, ')');

        return [$method, $arguments];
    }

    /**
     * @return list<AbstractExpression>
     */
    private function parseArguments(TokenStream $stream): array {
        $arguments = [];

        do {
            $arguments[] = $this->parser->parseExpression();
        } while ($stream->nextIf(Token::PUNCTUATION_TYPE, ','));

        return $arguments;
    }

    public function getTag(): string
    {
        return 'attrs';
    }
}

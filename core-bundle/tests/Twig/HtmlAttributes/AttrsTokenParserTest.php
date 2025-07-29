<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Tests\Twig\HtmlAttributes;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\Global\ContaoVariable;
use Contao\CoreBundle\Twig\HtmlAttributes\AttrsTokenParser;
use Contao\CoreBundle\Twig\Inspector\InspectorNodeVisitor;
use Contao\CoreBundle\Twig\Inspector\Storage;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\LoaderInterface;

class AttrsTokenParserTest extends TestCase
{
    public function testGetTag(): void
    {
        $tokenParser = new AttrsTokenParser();

        $this->assertSame('attrs', $tokenParser->getTag());
    }

    #[DataProvider('provideSources')]
    public function testCompilesAndOutputsAttributes(array $context, string $code, string $expectedOutput): void
    {
        $environment = new Environment($this->createMock(LoaderInterface::class));

        $environment->addExtension(
            new ContaoExtension(
                $environment,
                $this->createMock(ContaoFilesystemLoader::class),
                $this->createMock(ContaoCsrfTokenManager::class),
                $this->createMock(ContaoVariable::class),
                new InspectorNodeVisitor($this->createMock(Storage::class), $environment),
            ),
        );

        $environment->addTokenParser(new AttrsTokenParser());
        $environment->setLoader(new ArrayLoader(['template.html.twig' => $code]));

        $this->assertSame($expectedOutput, $environment->render('template.html.twig', $context));
    }

    public static function provideSources(): iterable
    {
        yield 'empty attributes' => [
            [],
            '{% attrs foo %}<hr{{ foo }}>',
            '<hr>',
        ];

        yield 'empty attributes with parameter from context' => [
            ['foo' => new HtmlAttributes(['data-bar' => 'baz'])],
            '{% attrs foo %}<hr{{ foo }}>',
            '<hr data-bar="baz">',
        ];

        yield 'empty attributes with inline parameter' => [
            [],
            '{% set foo = attrs().set(\'data-bar\', \'baz\') %}{% attrs foo %}<hr{{ foo }}>',
            '<hr data-bar="baz">',
        ];

        yield 'defining operations' => [
            ['value' => 2],
            '{% attrs foo addClass(\'bar\') set(\'data-value\', value) %}<hr{{ foo }}>',
            '<hr class="bar" data-value="2">',
        ];

        yield 'defining operations adding linebreaks and whitespaces' => [
            ['value' => 2],
            "{% attrs  foo\naddClass('bar')\n   set('data-value', value)  \n  %}<hr{{ foo }}>",
            '<hr class="bar" data-value="2">',
        ];

        yield 'defining operations merging with existing parameter from context' => [
            ['foo' => new HtmlAttributes(['class' => 'c1 c2'])],
            '{% attrs foo addClass(\'c3\') set(\'data-enable\') %}<hr{{ foo }}>',
            '<hr class="c3 c1 c2" data-enable>',
        ];

        yield 'defining operations merging with existing inline parameter' => [
            [],
            '{% set foo = attrs().addClass(\'c1 c2\') %}{% attrs foo addClass(\'c3\') set(\'data-enable\') %}<hr{{ foo }}>',
            '<hr class="c3 c1 c2" data-enable>',
        ];
    }
}

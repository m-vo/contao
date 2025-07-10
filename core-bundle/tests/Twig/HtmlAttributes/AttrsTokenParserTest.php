<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Tests\Twig\HtmlAttributes;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
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

        yield 'setting operations' => [
            ['value' => 2],
            '{% attrs foo addClass(\'bar\') set(\'data-value\', value) %}<hr{{ foo }}>',
            '<hr>',
        ];
    }
}

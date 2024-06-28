<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BackendTemplateStudioController extends AbstractBackendController
{
    public function __construct(
        private readonly ContaoFilesystemLoader $loader,
        private readonly FinderFactory $finder
    )
    {
    }

    #[Route(
        '/contao/template-studio',
        name: self::class,
        defaults: ['_scope' => 'backend'])
    ]
    public function __invoke(): Response
    {
        return $this->render(
            '@Contao/backend/template_studio/index.html.twig',
            [
                'title' => 'Template Studio',
                'headline' => 'Template Studio',
                'tree' => $this->getTemplateTree(),
            ]
        );
    }


    #[Route(
        '/contao/template-studio/_navigate',
        name: '_contao_template_studio_navigate',
        requirements: ['path' => '.*'],
        defaults: ['_scope' => 'backend', 'path' => '']
    )]
    public function _navigate(Request $request): Response
    {
        return $this->turboStream(
            '@Contao/backend/template_studio/stream/navigate.stream.html.twig',
            [
            ]
        );
    }

    private function getTemplateTree(): array
    {
        $finder = $this->finder->create();

        $prefixTree = [];

        foreach ($finder as $identifier => $chain) {
            $parts = explode('/', $identifier);
            $node = &$prefixTree;

            foreach ($parts as $part) {
                /** @phpstan-ignore isset.offset */
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }

                $node = &$node[$part];
            }

            // $node = [...$node, ...$chain];
        }

        return $prefixTree;
    }

}

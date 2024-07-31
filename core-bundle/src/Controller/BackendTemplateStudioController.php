<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Inspector\BlockInformation;
use Contao\CoreBundle\Twig\Inspector\BlockType;
use Contao\CoreBundle\Twig\Inspector\Inspector;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Source;

class BackendTemplateStudioController extends AbstractBackendController
{
    public function __construct(
        private readonly ContaoFilesystemLoader $loader,
        private readonly FinderFactory          $finder,
        private readonly Inspector              $inspector
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
        requirements: ['item' => '.*'],
        defaults: ['_scope' => 'backend', 'item' => '']
    )]
    public function _navigate(Request $request): Response
    {
        // todo: validate
        $item = $request->query->get('item');

        $identifier = ContaoTwigUtil::getIdentifier($item);

        $sources = array_map(
            fn(string $name): Source => $this->loader->getSourceContext($name),
            $this->loader->getInheritanceChains()[$identifier] ?? []
        );

        return $this->turboStream(
            '@Contao/backend/template_studio/stream/navigate.stream.html.twig',
            [
                'item' => $identifier,
                'sources' => $sources,
            ]
        );
    }

    #[Route(
        '/contao/template-studio/_block_info',
        name: '_contao_template_studio_block_info',
        requirements: ['item' => '.*', 'block' => '.*'],
        defaults: ['_scope' => 'backend', 'item' => '', 'block' => '']
    )]
    public function _block_info(Request $request): Response
    {
        // todo validate

        $item = $request->query->get('item');
        $block = $request->query->get('block');

        $first = $this->loader->getFirst(ContaoTwigUtil::getIdentifier($item));

//        $blockHierarchy = [];
//        $search = [$first];
//
//        if(null !== ($blockInformation = $this->inspector->inspectTemplate($first)->getBlock($block))) {
//            $blockHierarchy[] = [
//                'type' => 'first',
//                'name' => $first,
//                'friendly' => $blockInformation->isFriendly(),
//            ];
//        }
//
//        while($search) {
//            $templateInformation = $this->inspector->inspectTemplate(array_shift($search));
//
//            // Find first used template that defines the given block
//            foreach (array_reverse($templateInformation->getUses()) as $name => $overrides) {
//                $originalBlockName = array_flip($overrides)[$block] ?? $block;
//                $usedTemplate = $this->inspector->inspectTemplate($originalBlockName);
//
//                if (null === ($blockInformation = $usedTemplate->getBlock($name))) {
//                    continue;
//                }
//
//                $blockHierarchy[] = [
//                    'type' => 'use',
//                    'name' => $name,
//                    'originalBlockName' => $originalBlockName,
//                    'friendly' => $blockInformation->isFriendly(),
//                ];
//
//                // Also search transitive uses
//                // todo: handle overrides?
//                $search = [...$search, array_keys(...$usedTemplate->getUses())];
//
//                break;
//            }
//
//            //  Find block in extended template
//            if(null !== ($parent = $templateInformation->getExtends())) {
//                $parentTemplate = $this->inspector->inspectTemplate($parent);
//
//                if (null !== ($blockInformation = $parentTemplate->getBlock($block))) {
//                    $blockHierarchy[] = [
//                        'type' => 'extend',
//                        'name' => $parent,
//                        'friendly' => $blockInformation->isFriendly(),
//                    ];
//                }
//
//                // Also search transitive extends
//                $search = array_filter([...$search, $parentTemplate->getExtends()]);
//            }
//        }

        $blockHierarchy = array_values(
            array_map(
                static fn(BlockInformation $info): array => [
                    'target' => false,
                    'shadowed' => false,
                    'warning' => false,
                    'info' => $info,
                    'template' => [
                        'namespace' => ContaoTwigUtil::parseContaoName($info->getTemplateName())[0] ?? '?',
                        'identifier' => ContaoTwigUtil::getIdentifier($info->getTemplateName()),
                        'extension' => ContaoTwigUtil::getExtension($info->getTemplateName()),
                    ],
                ],
                array_filter(
                    $this->inspector->getBlockHierarchy($first, $block),
                    static fn(BlockInformation $hierarchy): bool => $hierarchy->getType() !== BlockType::transparent,
                )
            )
        );

        $numBlocks = \count($blockHierarchy);

        for ($i = 0; $i < $numBlocks; $i++) {
            if ($blockHierarchy[$i]['info']->getTemplateName() === $item) {
                $blockHierarchy[$i]['target'] = true;
                break;
            }
        }

        $shadowed = false;
        $lastOverwrite = null;

        for ($i = 0; $i < $numBlocks; $i++) {
            if ($blockHierarchy[$i]['info']->getType() === BlockType::overwrite) {
                $shadowed = true;

                if ($lastOverwrite !== null) {
                    $blockHierarchy[$lastOverwrite]['warning'] = true;
                    $blockHierarchy[$i]['shadowed'] = $shadowed;
                }

                $lastOverwrite = $i;

                continue;
            }

            $blockHierarchy[$i]['shadowed'] = $shadowed;

            if ($lastOverwrite !== null && $blockHierarchy[$i]['info']->getType() === BlockType::origin && !$blockHierarchy[$i]['info']->isPrototype()) {
                $blockHierarchy[$lastOverwrite]['warning'] = true;
            }
        }

        return $this->turboStream(
            '@Contao/backend/template_studio/stream/block_info.stream.html.twig',
            [
                'hierarchy' => $blockHierarchy,
                'block' => $block,
                'short_name' => ContaoTwigUtil::parseContaoName($item)[1],
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

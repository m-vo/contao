<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Inspector\BlockInformation;
use Contao\CoreBundle\Twig\Inspector\BlockType;
use Contao\CoreBundle\Twig\Inspector\InspectionException;
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
        private readonly FinderFactory $finder,
        private readonly Inspector $inspector,
    ) {
    }

    #[Route(
        '/contao/template-studio',
        name: 'contao_template_studio',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('@Contao/backend/template_studio/index.html.twig', [
            'title' => 'Template Studio',
            'headline' => 'Template Studio',
        ]);
    }

    /**
     * Render an editor tab for a given identifier.
     */
    #[Route(
        '/_contao/template-studio/resource/{identifier}',
        name: '_contao_template_studio_editor_tab',
        requirements: ['identifier' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function editorTab(string $identifier): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!($this->loader->getInheritanceChains()[$identifier] ?? false)) {
            return new Response('Could not find template identifier..', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $sources = array_map(
            fn (string $name): Source => $this->loader->getSourceContext($name),
            $this->loader->getInheritanceChains()[$identifier] ?? [],
        );

        return $this->render('@Contao/backend/template_studio/editor/add_editor_tab.stream.html.twig', [
            'identifier' => $identifier,
            'templates' => array_map(
                function (Source $source): array {
                    $templateNameInformation = $this->getTemplateNameInformation($source->getName());

                    return [
                        ...$templateNameInformation,
                        'path' => $source->getPath(),
                        'code' => $source->getCode(),
                    ];
                },
                $sources,
            ),
            'can_edit' => false,
        ]);
    }

    /**
     * Build a prefix tree of template identifiers.
     */
    #[Route(
        '/_contao/template-studio-tree',
        name: '_contao_template_studio_tree',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function tree(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $prefixTree = [];

        foreach ($this->finder->create() as $identifier => $extension) {
            $parts = explode('/', $identifier);
            $node = &$prefixTree;

            foreach ($parts as $part) {
                /** @phpstan-ignore isset.offset */
                if (!isset($node[$part])) {
                    $node[$part] = [];
                }

                $node = &$node[$part];
            }

            $hasUserTemplate = $this->loader->exists("@Contao_Global/$identifier.$extension");

            $leaf = new class($identifier, $hasUserTemplate) {
                public function __construct(
                    public readonly string $identifier,
                    public readonly bool $hasUserTemplate,
                ) {
                }
            };

            $node = [...$node, $leaf];
        }

        $sortRecursive = static function (&$node) use (&$sortRecursive): void {
            if (!\is_array($node)) {
                return;
            }

            ksort($node);

            foreach ($node as &$child) {
                $sortRecursive($child);
            }
        };

        $sortRecursive($prefixTree);

        // Don't show backend templates
        unset($prefixTree['backend']);

        // Apply opinionated ordering
        $prefixTree = ['content_element' => [], 'frontend_module' => [], 'component' => [], ...$prefixTree];

        return $this->render('@Contao/backend/template_studio/tree.html.twig', [
            'tree' => $prefixTree,
        ]);
    }

    /**
     * Resolve a logical template name and open a tab with the associated identifier.
     */
    #[Route(
        '/_contao/template-studio-follow',
        name: '_contao_template_studio_follow',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function follow(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (($logicalName = $request->get('name')) === null) {
            return new Response(
                'Malformed request - did you forget to add the "name" parameter?',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $identifier = ContaoTwigUtil::getIdentifier($logicalName);

        if (!$identifier) {
            return new Response('Could not retrieve template identifier.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->editorTab($identifier);
    }

    /**
     * Generate hierarchical block information for a given template and block name.
     */
    #[Route(
        '/_contao/template-studio-block-info',
        name: '_contao_template_studio_block_info',
        defaults: ['_scope' => 'backend'],
        methods: ['GET'],
    )]
    public function blockInfo(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (($blockName = $request->query->get('block')) === null ||
            ($logicalName = $request->query->get('name')) === null) {
            return new Response(
                'Malformed request - did you forget to add the "block" or "name" query parameter?',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $firstLogicalName = $this->loader->getFirst($logicalName);
        } catch (\LogicException) {
            return new Response('Could not find requested template.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $blockHierarchy = $this->inspector->getBlockHierarchy($firstLogicalName, $blockName);
        } catch (InspectionException) {
            return new Response('Could not retrieve requested block information.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Enrich data
        $blockHierarchy = array_values(
            array_map(
                fn (BlockInformation $info): array => [
                    'target' => false,
                    'shadowed' => false,
                    'warning' => false,
                    'info' => $info,
                    'template' => $this->getTemplateNameInformation($info->getTemplateName()),
                ],
                array_filter(
                    $blockHierarchy,
                    static fn (BlockInformation $hierarchy): bool => BlockType::transparent !== $hierarchy->getType(),
                ),
            ),
        );

        $numBlocks = \count($blockHierarchy);

        for ($i = 0; $i < $numBlocks; ++$i) {
            if ($blockHierarchy[$i]['info']->getTemplateName() === $logicalName) {
                $blockHierarchy[$i]['target'] = true;
                break;
            }
        }

        $shadowed = false;
        $lastOverwrite = null;

        for ($i = 0; $i < $numBlocks; ++$i) {
            if (BlockType::overwrite === $blockHierarchy[$i]['info']->getType()) {
                $shadowed = true;

                if (null !== $lastOverwrite) {
                    $blockHierarchy[$lastOverwrite]['warning'] = true;
                    $blockHierarchy[$i]['shadowed'] = true;
                }

                $lastOverwrite = $i;

                continue;
            }

            $blockHierarchy[$i]['shadowed'] = $shadowed;

            if (null !== $lastOverwrite && BlockType::origin === $blockHierarchy[$i]['info']->getType() && !$blockHierarchy[$i]['info']->isPrototype()) {
                $blockHierarchy[$lastOverwrite]['warning'] = true;
            }
        }

        return $this->render('@Contao/backend/template_studio/info/block_info.stream.html.twig', [
            'hierarchy' => $blockHierarchy,
            'block' => $blockName,
            'target_template' => $this->getTemplateNameInformation($logicalName),
        ]);
    }

    private function getTemplateNameInformation(string $logicalName): array
    {
        [$namespace, $shortName] = ContaoTwigUtil::parseContaoName($logicalName);

        return [
            'name' => $logicalName,
            'short_name' => $shortName ?? '?',
            'namespace' => $namespace ?? '?',
            'identifier' => ContaoTwigUtil::getIdentifier($shortName) ?? '?',
            'extension' => ContaoTwigUtil::getExtension($shortName) ?? '?',
        ];
    }
}

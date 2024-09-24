<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Controller;

use Contao\CoreBundle\Twig\ContaoTwigUtil;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\CoreBundle\Twig\Inspector\BlockInformation;
use Contao\CoreBundle\Twig\Inspector\BlockType;
use Contao\CoreBundle\Twig\Inspector\Inspector;
use Contao\CoreBundle\Twig\Loader\ContaoFilesystemLoader;
use Contao\CoreBundle\Twig\Studio\ActionContext;
use Contao\CoreBundle\Twig\Studio\ActionInterface;
use Contao\CoreBundle\Twig\Studio\ActionProviderInterface;
use Contao\CoreBundle\Twig\Studio\ActionSignature;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Source;

class BackendTemplateStudioController extends AbstractBackendController
{
    /**
     * @var array<string, ActionInterface>
     */
    private readonly array $actions;

    /**
     * @param iterable<int, ActionProviderInterface> $actionProviders
     */
    public function __construct(
        private readonly ContaoFilesystemLoader $loader,
        private readonly FinderFactory          $finder,
        private readonly Inspector              $inspector,
        private readonly string                 $projectDir,
        iterable                                $actionProviders = []
    )
    {
        $actions = [];

        foreach ($actionProviders as $provider) {
            foreach ($provider->getActions() as $action) {
                $actions[$action->getName()] = $action;
            }
        }

        $this->actions = $actions;
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
        '/contao/template-studio/resource/{identifier}',
        name: '_contao_template_studio_open',
        requirements: ['identifier' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['GET']
    )]
    public function open(Request $request, string $identifier): Response
    {
        // todo: validate - should this consider unset elements from the tree?
        $sources = array_map(
            fn(string $name): Source => $this->loader->getSourceContext($name),
            $this->loader->getInheritanceChains()[$identifier] ?? []
        );

        return $this->turboStream(
            '@Contao/backend/template_studio/stream/open_tab.stream.html.twig',
            [
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
                    $sources
                ),
                'actions' =>
                    array_map(
                        static fn(ActionInterface $action): string => $action->getName(),
                        array_filter(
                            [...$this->actions],
                            fn(ActionInterface $action): bool => $action->canExecute($this->createActionContext($request, $identifier)),
                        )
                    ),
            ]
        );
    }

    #[Route(
        '/contao/template-studio/resource',
        name: '_contao_template_studio_resolve_and_open',
        requirements: ['name' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['GET']
    )]
    public function resolveAndOpen(Request $request): Response
    {
        $identifier = ContaoTwigUtil::getIdentifier($request->get('name'));

        return $this->open($request, $identifier);
    }

    #[Route(
        '/contao/template-studio/resource/{identifier}',
        name: '_contao_template_studio_save',
        requirements: ['identifier' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['PUT']
    )]
    public function save(Request $request, string $identifier): Response
    {
        // todo: should save maybe also be an action and just have an additional key binding?
        $data = $request->getContent();

        // Get the file that an editor is allowed to edit
        $first = $this->loader->getFirst($identifier);

        if ((ContaoTwigUtil::parseContaoName($first)[0] ?? '') !== 'Contao_Global') {
            throw new \InvalidArgumentException(sprintf('There is no userland template for identifier "%s".', $identifier));
        }

        $sourceContext = $this->loader->getSourceContext($first);

        // todo use VFS for this
        $filesystem = new Filesystem();
        $filesystem->dumpFile($sourceContext->getPath(), $data);

        // todo: reparse file

        return $this->turboStream(
            '@Contao/backend/template_studio/stream/save.stream.html.twig',
            [
                'path' => Path::makeRelative($sourceContext->getPath(), $this->projectDir),
            ]
        );
    }

    #[Route(
        '/contao/template-studio/resource/{identifier}/action/{action}',
        name: '_contao_template_studio_action',
        requirements: ['identifier' => '.+', 'action' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['POST']
    )]
    public function action(Request $request, string $identifier, string $actionName): Response
    {
        if (null === ($action = ($this->actions[$actionName] ?? null))) {
            throw new \InvalidArgumentException(sprintf('The action "%s" is not defined.', $actionName));
        }

        $context = $this->createActionContext($request, $identifier);

        if (!$action->canExecute($context)) {
            throw new \RuntimeException(sprintf('The action "%s" cannot be executed in the current context.', $actionName));
        }

        $result = $action->execute($context);

        if ($result->hasStep()) {
            return $this->stream(...$result->getStep());
        }

        // todo: reload stuff?
        return $this->stream(
            '@Contao/backend/template_studio/action/result.stream.html.twig',
            [
                'success' => $result->isSuccessful(),
                'message' => $result->getMessage(),
            ]
        );
    }

    #[Route(
        '/contao/template-studio/block_info',
        name: '_contao_template_studio_block_info',
        requirements: ['name' => '.+', 'block' => '.+'],
        defaults: ['_scope' => 'backend'],
        methods: ['GET']
    )]
    public function block_info(Request $request): Response
    {
        // todo validate

        $name = $request->query->get('name');
        $block = $request->query->get('block');

        $first = $this->loader->getFirst(ContaoTwigUtil::getIdentifier($name));

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
                fn(BlockInformation $info): array => [
                    'target' => false,
                    'shadowed' => false,
                    'warning' => false,
                    'info' => $info,
                    'template' => $this->getTemplateNameInformation($info->getTemplateName()),
                ],
                array_filter(
                    $this->inspector->getBlockHierarchy($first, $block),
                    static fn(BlockInformation $hierarchy): bool => $hierarchy->getType() !== BlockType::transparent,
                )
            )
        );

        $numBlocks = \count($blockHierarchy);

        for ($i = 0; $i < $numBlocks; $i++) {
            if ($blockHierarchy[$i]['info']->getTemplateName() === $name) {
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
                    $blockHierarchy[$i]['shadowed'] = true;
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
                // todo: shall we use getTemplateNameInformation() here as well?
                'short_name' => ContaoTwigUtil::parseContaoName($name)[1],
            ]
        );
    }

    private function getTemplateTree(): array
    {
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

            $leaf = new class($identifier, $this->loader->exists("@Contao_Global/$identifier.$extension")) {
                public function __construct(
                    public readonly string $identifier,
                    public readonly bool   $isCustomized,
                )
                {
                }
            };

            $node = [...$node, $leaf];
        }

        $sortRecursive = static function (&$node) use (&$sortRecursive): void {
            if (!is_array($node)) {
                return;
            }

            uksort($node, static function ($a, $b) {
                if (is_array($a)) {
                    return -1;
                }

                return $a <=> $b;
            });

            foreach ($node as &$child) {
                $sortRecursive($child);
            }
        };

        $sortRecursive($prefixTree);

        // todo: event to adjust order/remove things instead of hardcoding?
        unset($prefixTree['backend']);

        return array_merge(['content_element' => [], 'frontend_module' => [], 'component' => []], $prefixTree);
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

    private function createActionContext(Request $request, string $identifier): ActionContext
    {
        return new ActionContext([
            'identifier' => $identifier,
            'request' => $request,
        ]);
    }
}

<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Inspector;

class BlockInformation
{
    public function __construct(
        private readonly string    $templateName,
        private readonly string    $blockName,
        private readonly BlockType $type,
        private readonly bool $isPrototype = false,
    )
    {
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function getBlockName(): string
    {
        return $this->blockName;
    }

    public function getType(): BlockType
    {
        return $this->type;
    }

    public function isPrototype(): bool {
        return $this->isPrototype;
    }

    public function __toString(): string
    {
        return $this->getBlockName();
    }
}

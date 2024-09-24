<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

class ActionContext
{
    public function __construct(private readonly array $parameters)
    {
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }
}

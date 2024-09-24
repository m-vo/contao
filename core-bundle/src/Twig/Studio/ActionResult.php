<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Studio;

final class ActionResult
{
    private function __construct(
        private readonly bool|null   $success,
        private readonly string|null $message = null,
        private readonly array|null  $step = null,
    )
    {
    }

    public static function streamStep(string $template, ActionContext $actionContext): self
    {
        return new self(null, step: [$template, $actionContext->getParameters()]);
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function error(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccessful(): bool|null
    {
        return $this->success;
    }

    public function getMessage(): string|null
    {
        return $this->message;
    }

    public function hasStep(): bool
    {
        return $this->step !== null;
    }

    /**
     * @return array{0: string, 1: array}|null
     */
    public function getStep(): array|null
    {
        return $this->step;
    }
}

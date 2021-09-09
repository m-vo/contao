<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class TwigThemeDirectoriesEvent extends Event
{
    /**
     * @var array<string, string>
     */
    private $directories;

    public function __construct(array $directories)
    {
        $this->directories = $directories;
    }

    /**
     * @return array<string, string>
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    public function setDirectories(array $directories): void
    {
        $this->directories = $directories;
    }
}

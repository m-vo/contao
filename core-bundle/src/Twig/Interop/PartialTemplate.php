<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Twig\Interop;

use Contao\Template;

/**
 * @internal
 */
class PartialTemplate extends Template
{
    public function __construct(string $template, array $blocks, array $context)
    {
        parent::__construct($template);

        $this->arrData = $context;
        $this->arrBlocks = $blocks;
        $this->arrBlockNames = array_keys($blocks);

        // Do not delegate to Twig to prevent an endless loop
        $this->blnDelegateToTwig = false;
    }

    public function parse(): string
    {
        // Do not execute 'parseTemplate' hook here

        return $this->inherit();
    }
}

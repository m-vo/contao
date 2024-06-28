<?php
declare(strict_types=1);

namespace Contao\CoreBundle\Twig\Inspector;

enum BlockType: string
{
    /**
     * The block was defined inside the template.
     */
    case origin = 'origin';

    /**
     * The block overwrites a block from another template. The parent() function is not used.
     */
    case overwrite = 'overwrite';

    /**
     * The block enhances a block from another template. The parent() function is used.
     */
    case enhance = 'enhance';

    /**
     * The block was defined in another template and is not altered.
     */
    case transparent = 'transparent';
}

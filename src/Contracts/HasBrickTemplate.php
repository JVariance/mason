<?php

declare(strict_types=1);

namespace Awcodes\Mason\Contracts;

use Awcodes\Mason\Support\BrickTemplate;

/**
 * A container brick that comes with bricks inside it. Some of them can be locked: they cannot be removed (only
 * hidden) or moved. The locks always follow the template in the code, not the stored content.
 */
interface HasBrickTemplate extends ContainsBricks
{
    /**
     * @return list<BrickTemplate>
     */
    public static function getTemplate(): array;
}

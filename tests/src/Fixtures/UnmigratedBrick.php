<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;

/**
 * Raised to version 2 without a migration.
 */
class UnmigratedBrick extends Brick
{
    public static function getId(): string
    {
        return 'unmigrated-brick';
    }

    public static function getVersion(): int
    {
        return 2;
    }
}

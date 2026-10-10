<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\ContainsBricks;
use Awcodes\Mason\Support\BrickChildren;
use Awcodes\Mason\Support\BrickContext;

/**
 * Gives the bricks inside it the next level (BrickContext).
 */
class LevelContainerBrick extends Brick implements ContainsBricks
{
    public static function getId(): string
    {
        return 'level-container-brick';
    }

    public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
    {
        return '<div class="level">' . $children->with(['level' => BrickContext::get('level', 1) + 1])->toHtml() . '</div>';
    }

    public static function getAllowedChildren(): ?array
    {
        return null;
    }
}

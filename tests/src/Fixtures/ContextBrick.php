<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Support\BrickContext;

/**
 * Shows its uid and the level it gets from the bricks around it.
 */
class ContextBrick extends Brick
{
    public static function getId(): string
    {
        return 'context-brick';
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return '<p data-uid="' . BrickContext::uid() . '" data-level="' . BrickContext::get('level', 1) . '"></p>';
    }
}

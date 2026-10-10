<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\ContainsBricks;
use Awcodes\Mason\Support\BrickChildren;

/**
 * A container with two areas, told apart by the bricks' "area" attribute.
 */
class AreaContainerBrick extends Brick implements ContainsBricks
{
    public static function getId(): string
    {
        return 'area-container-brick';
    }

    public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
    {
        $areas = array_map(
            fn (string $area): string => '<section class="area-' . $area . '" ' . $children->dropTarget(['area' => $area])->toHtml() . '>'
                . $children->where('area', $area)->toHtml()
                . $children->dropZone(['area' => $area])->toHtml()
                . '</section>',
            ['a', 'b'],
        );

        return '<div class="areas">' . implode('', $areas) . '</div>';
    }

    public static function getAllowedChildren(): ?array
    {
        return null;
    }
}

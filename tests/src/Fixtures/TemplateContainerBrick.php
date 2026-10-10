<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\HasBrickTemplate;
use Awcodes\Mason\Support\BrickChildren;
use Awcodes\Mason\Support\BrickTemplate;

/**
 * Comes with a locked intro and a free extra brick.
 */
class TemplateContainerBrick extends Brick implements HasBrickTemplate
{
    public static function getId(): string
    {
        return 'template-container-brick';
    }

    public static function getTemplate(): array
    {
        return [
            BrickTemplate::make('intro', TestBrick::class)->config(['title' => 'Intro'])->lockRemoval()->lockMovement(),
            BrickTemplate::make('extra', SimpleBrick::class),
        ];
    }

    public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
    {
        return '<div class="template-container">' . $children->toHtml() . '</div>';
    }

    public static function getAllowedChildren(): ?array
    {
        return null;
    }
}

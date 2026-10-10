<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\ContainsBricks;
use Awcodes\Mason\Support\BrickChildren;
use Awcodes\Mason\Support\BrickTree;

/**
 * Version 3: version 1 stored "title" (now "heading"), version 2 kept its intro in the config (now a brick inside).
 */
class VersionedBrick extends Brick implements ContainsBricks
{
    public static function getId(): string
    {
        return 'versioned-brick';
    }

    public static function getVersion(): int
    {
        return 3;
    }

    public static function getMigrations(): array
    {
        return [
            1 => function (array $brick): array {
                $brick['attrs']['config']['heading'] = $brick['attrs']['config']['title'] ?? null;
                unset($brick['attrs']['config']['title']);

                return $brick;
            },
            2 => function (array $brick): array {
                $brick['content'] = [[
                    'type' => 'masonBrick',
                    'attrs' => [
                        'id' => 'test-brick',
                        'uid' => BrickTree::derivedUid((string) ($brick['attrs']['uid'] ?? ''), 'intro'),
                        'config' => ['title' => $brick['attrs']['config']['intro'] ?? null],
                    ],
                ], ...$brick['content'] ?? []];
                unset($brick['attrs']['config']['intro']);

                return $brick;
            },
        ];
    }

    public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
    {
        return '<div class="versioned" data-heading="' . e($config['heading'] ?? '') . '">' . $children->toHtml() . '</div>';
    }

    public static function getAllowedChildren(): ?array
    {
        return null;
    }
}

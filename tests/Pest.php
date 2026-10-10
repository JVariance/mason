<?php

declare(strict_types=1);

use Awcodes\Mason\Support\BrickTree;
use Awcodes\Mason\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(
    TestCase::class,
    LazilyRefreshDatabase::class,
)->in(__DIR__);

/**
 * Saved content without the uids Mason gives every brick, to compare it with what was filled in.
 */
function withoutUids(?array $content): ?array
{
    return $content === null ? null : BrickTree::map($content, function (array $block): array {
        unset($block['attrs']['uid']);

        return $block;
    });
}

<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Awcodes\Mason\Brick;
use LogicException;

/**
 * Brings stored bricks up to their brick's current version (Brick::getVersion(), Brick::getMigrations()).
 *
 * The field and the renderers upgrade content when they read it, so content saved with an older version, including
 * old revisions, keeps working. The upgraded content is stored the next time it is saved.
 */
class BrickMigrator
{
    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, class-string<Brick>>  $bricks  brick classes by id
     * @return array<int, mixed>
     */
    public static function upgrade(array $blocks, array $bricks): array
    {
        // Parents first: a step may add bricks inside, which are then upgraded as well
        return BrickTree::map($blocks, function (array $block) use ($bricks): array {
            $brick = $bricks[$block['attrs']['id'] ?? ''] ?? null;

            return $brick === null ? $block : static::upgradeBrick($block, $brick);
        });
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  class-string<Brick>  $brick
     * @return array<string, mixed>
     */
    public static function upgradeBrick(array $block, string $brick): array
    {
        $target = $brick::getVersion();
        $version = static::version($block);

        if ($version >= $target) {
            return $block;
        }

        $migrations = $brick::getMigrations();

        for (; $version < $target; $version++) {
            $step = $migrations[$version] ?? throw new LogicException("Brick [{$brick}] has no migration from version {$version}.");
            $block = $step($block);
        }

        $block['attrs']['v'] = $target;

        return $block;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public static function version(array $block): int
    {
        $version = $block['attrs']['v'] ?? 1;

        return is_int($version) && $version > 0 ? $version : 1;
    }
}

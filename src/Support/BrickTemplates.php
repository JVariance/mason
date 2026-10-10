<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\HasBrickTemplate;

/**
 * Applies the templates of container bricks (HasBrickTemplate) to content and tells what is locked.
 */
class BrickTemplates
{
    /**
     * The template entry a brick inside the container belongs to, if any.
     *
     * @param  array<string, mixed>|null  $container
     * @param  array<string, mixed>  $brick
     * @param  array<string, class-string<Brick>>  $bricks  brick classes by id
     */
    public static function entryFor(?array $container, array $brick, array $bricks): ?BrickTemplate
    {
        $key = $brick['attrs']['template'] ?? null;

        if (! is_string($key) || $container === null) {
            return null;
        }

        foreach (static::template($container, $bricks) as $entry) {
            if ($entry->key === $key && ($brick['attrs']['id'] ?? null) === $entry->brick::getId()) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Locks of the brick at the path.
     *
     * @param  array<int, mixed>  $blocks
     * @param  array<string, class-string<Brick>>  $bricks
     * @return array{removal: bool, movement: bool}
     */
    public static function locks(array $blocks, string | int $path, array $bricks): array
    {
        $path = BrickTree::parse($path);
        $brick = BrickTree::get($blocks, $path);
        $container = count($path) > 1 ? BrickTree::get($blocks, array_slice($path, 0, -1)) : null;
        $entry = $brick === null ? null : static::entryFor($container, $brick, $bricks);

        return [
            'removal' => $entry?->isRemovalLocked() ?? false,
            'movement' => $entry?->isMovementLocked() ?? false,
        ];
    }

    /**
     * A new container gets the bricks of its template; one that lacks a brick it cannot do without gets it back.
     *
     * @param  array<int, mixed>  $blocks
     * @param  array<string, class-string<Brick>>  $bricks
     * @return array<int, mixed>
     */
    public static function ensure(array $blocks, array $bricks): array
    {
        return BrickTree::map($blocks, function (array $block) use ($bricks): array {
            $template = static::template($block, $bricks);

            if ($template === []) {
                return $block;
            }

            $uid = is_string($block['attrs']['uid'] ?? null) ? $block['attrs']['uid'] : null;

            if (! array_key_exists('content', $block)) {
                $block['content'] = array_map(fn (BrickTemplate $entry): array => $entry->toBlock($uid), $template);

                return $block;
            }

            $content = is_array($block['content']) ? array_values($block['content']) : [];

            foreach ($template as $position => $entry) {
                $present = collect($content)->contains(fn (mixed $child): bool => is_array($child) && static::entryFor($block, $child, $bricks)?->key === $entry->key);

                if ($entry->isRemovalLocked() && ! $present) {
                    array_splice($content, min($position, count($content)), 0, [$entry->toBlock($uid)]);
                }
            }

            $block['content'] = $content;

            return $block;
        });
    }

    /**
     * Marks the locks on the bricks (attrs.lock) for the editor; the field removes them before saving.
     *
     * @param  array<int, mixed>  $blocks
     * @param  array<string, class-string<Brick>>  $bricks
     * @return array<int, mixed>
     */
    public static function annotate(array $blocks, array $bricks, ?array $container = null): array
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $entry = static::entryFor($container, $block, $bricks);
            unset($block['attrs']['lock']);

            if ($entry?->isRemovalLocked() || $entry?->isMovementLocked()) {
                $block['attrs']['lock'] = ['removal' => $entry->isRemovalLocked(), 'movement' => $entry->isMovementLocked()];
            }

            if (is_array($block['content'] ?? null)) {
                $block['content'] = static::annotate($block['content'], $bricks, $block);
            }

            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, class-string<Brick>>  $bricks
     * @return list<BrickTemplate>
     */
    protected static function template(array $block, array $bricks): array
    {
        $brick = $bricks[$block['attrs']['id'] ?? ''] ?? null;

        return $brick !== null && is_subclass_of($brick, HasBrickTemplate::class) ? $brick::getTemplate() : [];
    }
}

<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Closure;

/**
 * What a brick knows about where it is rendered, without changing toHtml()'s signature.
 *
 * - attributes() and uid() are those of the brick being rendered, e.g. for ids that stay the same when the content
 *   around it changes.
 * - get() reads values a container passed to the bricks inside it (BrickChildren::with()), e.g. a heading level or
 *   the id a group is labelled by. They reach every brick inside, also those inside further containers.
 *
 * The renderers set it while they render; outside of that it is empty.
 */
final class BrickContext
{
    /**
     * @var list<array{attrs: array<string, mixed>, values: array<string, mixed>}>
     */
    private static array $stack = [];

    /**
     * Renders a brick: during the callback, attributes() are the brick's, and values() those of the brick around it.
     *
     * @template T
     *
     * @param  array<string, mixed>  $block
     * @param  Closure(): T  $render
     * @return T
     */
    public static function render(array $block, Closure $render): mixed
    {
        return self::push(is_array($block['attrs'] ?? null) ? $block['attrs'] : [], self::values(), $render);
    }

    /**
     * Gives the bricks rendered during the callback these values in addition to the current ones, e.g. content
     * rendered by another renderer inside a brick.
     *
     * @template T
     *
     * @param  array<string, mixed>  $values
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function with(array $values, Closure $callback): mixed
    {
        return self::push(self::attributes(), [...self::values(), ...$values], $callback);
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributes(): array
    {
        return self::current()['attrs'] ?? [];
    }

    public static function uid(): ?string
    {
        $uid = self::attributes()['uid'] ?? null;

        return is_string($uid) && $uid !== '' ? $uid : null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, self::values()) ? self::values()[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return self::current()['values'] ?? [];
    }

    /**
     * @return array{attrs: array<string, mixed>, values: array<string, mixed>}|null
     */
    private static function current(): ?array
    {
        return self::$stack === [] ? null : self::$stack[array_key_last(self::$stack)];
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $values
     * @param  Closure(): T  $callback
     * @return T
     */
    private static function push(array $attrs, array $values, Closure $callback): mixed
    {
        self::$stack[] = ['attrs' => $attrs, 'values' => $values];

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }
}

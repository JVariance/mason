<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use ArrayIterator;
use Closure;
use Countable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use IteratorAggregate;
use Traversable;

/**
 * The bricks inside a container brick, rendered.
 *
 * A container's view either outputs them all at once or loops over them to wrap
 * each one, and should output dropZone() after them: in the editor that is where
 * bricks are dropped into the container, everywhere else it is empty.
 *
 * A container with several areas (e.g. slots) tells them apart by an attribute
 * of its bricks: dropZone(['slot' => 'a']) inserts with that attribute, and
 * dropTarget(['slot' => 'a']) marks the element around an area, so that bricks
 * dropped next to its bricks get the attribute as well.
 *
 * @implements IteratorAggregate<int, BrickChild>
 */
class BrickChildren implements Countable, Htmlable, IteratorAggregate
{
    /**
     * @param  list<BrickChild>  $children
     * @param  (Closure(array<string, mixed> $attrs): string)|null  $zone  renders a drop zone in the editor
     * @param  (Closure(array<string, mixed> $attrs): string)|null  $target  renders the attributes of a drop target in the editor
     */
    public function __construct(
        protected array $children = [],
        protected string $dropZone = '',
        protected ?Closure $zone = null,
        protected ?Closure $target = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attrs  attributes a brick inserted here gets, e.g. ['slot' => 'a']
     */
    public function dropZone(array $attrs = []): Htmlable
    {
        if ($attrs === [] || $this->zone === null) {
            return new HtmlString($attrs === [] ? $this->dropZone : '');
        }

        return new HtmlString(($this->zone)($attrs));
    }

    /**
     * HTML attributes for the element around an area of the container; empty outside the editor.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function dropTarget(array $attrs): Htmlable
    {
        return new HtmlString($this->target === null ? '' : ($this->target)($attrs));
    }

    /**
     * The bricks rendered with values for them and the bricks inside them (BrickContext::get()).
     *
     * @param  array<string, mixed>  $values
     */
    public function with(array $values): self
    {
        return new self(
            array_map(fn (BrickChild $child): BrickChild => $child->with($values), $this->children),
            $this->dropZone,
            $this->zone,
            $this->target,
        );
    }

    /**
     * The bricks whose attribute has the value, e.g. those in one slot.
     */
    public function where(string $attribute, mixed $value): self
    {
        return new self(
            array_values(array_filter($this->children, fn (BrickChild $child): bool => $child->getAttribute($attribute) === $value)),
            '',
            $this->zone,
            $this->target,
        );
    }

    public function isEmpty(): bool
    {
        return $this->children === [];
    }

    public function count(): int
    {
        return count($this->children);
    }

    /**
     * @return Traversable<int, BrickChild>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->children);
    }

    public function toHtml(): string
    {
        return implode('', array_map(fn (BrickChild $child): string => $child->toHtml(), $this->children)) . $this->dropZone;
    }
}

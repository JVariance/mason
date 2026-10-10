<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * A rendered brick inside a container brick. In the editor its HTML includes the brick's controls.
 */
class BrickChild implements Htmlable, Stringable
{
    protected ?string $rendered = null;

    /**
     * @param  array<string, mixed>  $block
     * @param  string|(Closure(array<string, mixed> $values): string)  $html  the HTML, or how to render it with values
     *                                                                        for the bricks inside (BrickContext)
     */
    public function __construct(
        protected array $block,
        protected string | Closure $html,
    ) {}

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * The brick rendered with values for it and the bricks inside it, e.g. a heading level (BrickContext::get()).
     *
     * @param  array<string, mixed>  $values
     */
    public function with(array $values): self
    {
        $html = $this->html;

        if (! $html instanceof Closure || $values === []) {
            return $this;
        }

        return new self($this->block, fn (array $more): string => $html([...$values, ...$more]));
    }

    public function getId(): ?string
    {
        return $this->block['attrs']['id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return is_array($this->block['attrs']['config'] ?? null) ? $this->block['attrs']['config'] : [];
    }

    /**
     * An attribute of the stored brick, e.g. layout data that only the container interprets.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return data_get($this->block['attrs'] ?? [], $key, $default);
    }

    /**
     * Rendered when first needed, so that a container's values (BrickContext) reach it.
     */
    public function toHtml(): string
    {
        $html = $this->html;

        return $this->rendered ??= $html instanceof Closure ? $html([]) : $html;
    }
}

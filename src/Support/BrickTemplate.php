<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Awcodes\Mason\Brick;

/**
 * A brick a container comes with (HasBrickTemplate). Its key is stored on the brick (attrs.template), so that the
 * container knows it later, and the locks follow from the template in the code.
 */
final class BrickTemplate
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    private bool $removalLocked = false;

    private bool $movementLocked = false;

    /**
     * @param  class-string<Brick>  $brick
     */
    private function __construct(
        public readonly string $key,
        public readonly string $brick,
    ) {}

    /**
     * @param  class-string<Brick>  $brick
     */
    public static function make(string $key, string $brick): self
    {
        return new self($key, $brick);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function config(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Further attributes, e.g. the area of the container it sits in.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * The brick cannot be removed. Authors can hide it instead (attrs.hidden), and it is added again if it is missing.
     */
    public function lockRemoval(bool $condition = true): self
    {
        $this->removalLocked = $condition;

        return $this;
    }

    /**
     * The brick cannot be moved.
     */
    public function lockMovement(bool $condition = true): self
    {
        $this->movementLocked = $condition;

        return $this;
    }

    public function isRemovalLocked(): bool
    {
        return $this->removalLocked;
    }

    public function isMovementLocked(): bool
    {
        return $this->movementLocked;
    }

    /**
     * The brick to store. With the container's uid it gets a uid derived from it, the same every time.
     *
     * @return array<string, mixed>
     */
    public function toBlock(?string $containerUid = null): array
    {
        $attrs = [
            ...$this->attributes,
            'id' => $this->brick::getId(),
            'config' => $this->config,
            'template' => $this->key,
        ];

        if ($containerUid !== null && $containerUid !== '') {
            $attrs['uid'] = BrickTree::derivedUid($containerUid, 'template:' . $this->key);
        }

        return ['type' => 'masonBrick', 'attrs' => $attrs];
    }
}

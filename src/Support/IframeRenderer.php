<?php

declare(strict_types=1);

namespace Awcodes\Mason\Support;

use Awcodes\Mason\Concerns\HasBricks;
use Filament\Support\Concerns\EvaluatesClosures;

class IframeRenderer
{
    use EvaluatesClosures;
    use HasBricks;

    /**
     * Preview data of the field (Mason::previewData()); null without, as before.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $data = null;

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function __construct(protected array $blocks = [])
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public static function make(array $blocks = []): static
    {
        return app(static::class, ['blocks' => $blocks]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function setBlocks(array $blocks): static
    {
        $this->blocks = $blocks;

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function data(?array $data): static
    {
        $this->data = $data === [] ? null : $data;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Get the HTML for a single block
     *
     * @param  array<string, mixed>  $block
     * @param  string  $path  where the block sits, for the controls of the bricks inside it
     */
    public function getBlockHtml(array $block, string $path = ''): ?string
    {
        return BrickContext::render($block, fn (): ?string => $this->renderBlockHtml($block, $path));
    }

    /**
     * Render the full iframe HTML document
     */
    public function toHtml(?string $layout = null): string
    {
        $blocks = array_map(
            fn (array $block, int $index): array => $this->getBlockData($block, (string) $index),
            $blocks = $this->upgradeBricks($this->blocks),
            array_keys($blocks),
        );

        // Use the provided layout, fallback to config, then default
        $layoutToUse = $layout ?? config('mason.preview.layout');

        // If a layout is configured, use it with the preview content slotted in
        if ($layoutToUse) {
            return view($layoutToUse, [
                'blocks' => $blocks,
            ])->render();
        }

        // Otherwise, use the default full HTML document
        return view('mason::iframe-preview', [
            'blocks' => $blocks,
        ])->render();
    }

    /**
     * @param  array<string, mixed>  $block
     */
    protected function renderBlockHtml(array $block, string $path): ?string
    {
        if (($block['type'] ?? null) !== 'masonBrick') {
            return null;
        }

        $id = $block['attrs']['id'] ?? null;
        $config = $block['attrs']['config'] ?? [];

        if (blank($id)) {
            return null;
        }

        // getBrick() resolves through getCachedBricks(), which unwraps BrickGroups.
        // Walking getBricks() directly skipped every brick registered inside a
        // group, and fatalled outright once a group reached the brick list.
        $brick = $this->getBrick($id);

        if ($brick === null) {
            return view('mason::components.unregistered-brick', ['label' => $id])->render();
        }

        $container = $this->getContainerBrick($id);

        if ($container !== null) {
            return $container::toHtmlWithChildren($config, $this->getChildren($block, $path), data: $this->data);
        }

        return $brick::toHtml($config, data: $this->data);
    }

    /**
     * The bricks inside a container, each wrapped in the editor's controls, and the zone to drop more into it.
     *
     * @param  array<string, mixed>  $block
     */
    protected function getChildren(array $block, string $path): BrickChildren
    {
        $content = array_values(array_filter(
            is_array($block['content'] ?? null) ? $block['content'] : [],
            fn (mixed $child): bool => is_array($child) && ($child['type'] ?? null) === 'masonBrick',
        ));

        $children = array_map(function (array $child, int $index) use ($block, $path, $content): BrickChild {
            $childPath = ltrim($path . '.' . $index, '.');

            return new BrickChild($child, fn (array $values): string => BrickContext::with($values, fn (): string => view('mason::components.preview-block', [
                'block' => $this->getBlockData($child, $childPath, BrickTemplates::entryFor($block, $child, $this->getCachedBricks())),
                'total' => count($content),
            ])->render()));
        }, $content, array_keys($content));

        $zone = fn (array $attrs = [], ?bool $isEmpty = null): string => view('mason::components.drop-zone', [
            'parent' => $path,
            'index' => count($content),
            'isEmpty' => $isEmpty ?? $content === [],
            'attrs' => $attrs,
        ])->render();

        return new BrickChildren(
            $children,
            $zone(),
            // An area of the container: empty when none of the container's bricks has the attributes
            fn (array $attrs): string => $zone($attrs, ! collect($content)->contains(
                fn (array $child): bool => collect($attrs)->every(fn (mixed $value, string $key): bool => data_get($child['attrs'] ?? [], $key) === $value),
            )),
            fn (array $attrs): string => 'data-drop-target data-drop-parent="' . e($path) . '" data-drop-attrs="' . e(json_encode($attrs)) . '"',
        );
    }

    /**
     * What the preview views need to render a block with its controls.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    protected function getBlockData(array $block, string $path, ?BrickTemplate $template = null): array
    {
        $id = $block['attrs']['id'] ?? null;

        return [
            // Locks follow the container's template, not what the browser sent
            'template' => $template?->key,
            'removalLocked' => $template?->isRemovalLocked() ?? false,
            'movementLocked' => $template?->isMovementLocked() ?? false,
            'hidden' => ($block['attrs']['hidden'] ?? false) === true,
            'index' => (int) str($path)->afterLast('.')->toString(),
            'path' => $path,
            'id' => $id,
            'config' => $block['attrs']['config'] ?? [],
            'html' => $this->getBlockHtml($block, $path),
            'label' => $this->getBlockLabel($id),
            'accepts' => $this->isContainerBrick($id) ? $this->getAcceptedBrickIds($id) : null,
        ];
    }

    /**
     * Get the label for a block
     */
    protected function getBlockLabel(?string $id): string
    {
        if (blank($id)) {
            return 'Unknown Brick';
        }

        $brick = $this->getBrick($id);

        if ($brick === null) {
            return 'Unknown Brick';
        }

        return $brick::getLabel();
    }
}

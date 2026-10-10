<?php

declare(strict_types=1);

namespace Awcodes\Mason;

use Awcodes\Mason\Actions\BrickAction;
use Awcodes\Mason\Concerns\HasBricks;
use Awcodes\Mason\Concerns\HasSidebar;
use Awcodes\Mason\Support\BrickCommand;
use Awcodes\Mason\Support\BrickMigrator;
use Awcodes\Mason\Support\BrickTemplates;
use Awcodes\Mason\Support\BrickTree;
use Awcodes\Mason\Support\RenderContext;
use Closure;
use Filament\Forms\Components\Concerns\HasExtraInputAttributes;
use Filament\Forms\Components\Contracts\CanBeLengthConstrained;
use Filament\Forms\Components\Field;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Filament\Support\Concerns\HasPlaceholder;
use Livewire\Component;

class Mason extends Field implements CanBeLengthConstrained
{
    use \Filament\Forms\Components\Concerns\CanBeLengthConstrained;
    use HasBricks;
    use HasExtraAlpineAttributes;
    use HasExtraInputAttributes;
    use HasPlaceholder;
    use HasSidebar;

    protected string $view = 'mason::mason';

    protected bool | Closure | null $isJson = null;

    protected bool | Closure | null $shouldDblClickToEdit = null;

    protected string | Closure | null $previewLayout = null;

    protected string | Closure | null $defaultColorMode = null;

    protected bool | Closure $showColorModeToggle = false;

    protected bool | Closure $isStructureLocked = false;

    /**
     * @var array<string, mixed> | Closure
     */
    protected array | Closure $previewData = [];

    /**
     * @var array<int, mixed> | Closure | null
     */
    protected array | Closure | null $lockedStructureReference = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (Mason $component, $state) {
            if (! $state) {
                return null;
            }

            // Ensure the state is an array
            if (! is_array($state)) {
                $state = [];
            }

            if (array_key_exists('content', $state)) {
                $state = $state['content'];
            }

            // Older versions of bricks are brought up to date first, then bricks from templates are added; all get uids
            $state = BrickTree::ensureUids(BrickTemplates::ensure($component->upgradeBricks($state), $component->getCachedBricks()));
            $state = BrickTemplates::annotate($state, $component->getCachedBricks());

            if ($component->hasOutline()) {
                $state = BrickTree::map($state, function (array $block) use ($component): array {
                    if (($block['type'] ?? null) !== 'masonBrick') {
                        return $block;
                    }

                    $block['attrs']['outlineLabel'] = $component->getBrickOutlineLabel(
                        $block['attrs']['id'] ?? '',
                        is_array($block['attrs']['config'] ?? null) ? $block['attrs']['config'] : [],
                    );

                    return $block;
                });
            }

            $component->state($state);
        });

        // The editor adds, moves and removes bricks in the browser, so a locked structure is checked again on save
        $this->rule(
            fn (Mason $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if (! BrickTree::hasSameStructure(is_array($value) ? $value : [], $component->getLockedStructureReference() ?? [])) {
                    $fail(__('mason::mason.validation.locked_structure'));
                }
            },
            fn (Mason $component): bool => $component->isStructureLocked() && $component->getLockedStructureReference() !== null,
        );

        $this->afterStateUpdated(function (Mason $component, Component $livewire): void {
            $livewire->validateOnly($component->getStatePath());
        });

        $this->dehydrateStateUsing(function (Mason $component, $state): ?array {
            if (! $state || ! is_array($state)) {
                return null;
            }

            // Bricks a template cannot do without are added again (the editor removes bricks in the browser)
            $state = BrickTemplates::ensure($state, $component->getCachedBricks());

            // Remove preview data before saving; uids are checked once more, so that no two bricks share one
            return BrickTree::map(BrickTree::ensureUids($state), function (array $block): array {
                if (($block['type'] ?? null) !== 'masonBrick') {
                    return $block;
                }

                unset($block['attrs']['label']);
                unset($block['attrs']['preview']);
                unset($block['attrs']['outlineLabel']);
                unset($block['attrs']['lock']);

                return $block;
            });
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function getBrickOutlineLabel(string $id, array $config): ?string
    {
        $brick = $this->getBrick($id);

        if (! $this->hasOutline() || blank($brick)) {
            return null;
        }

        $label = $brick::getOutlineLabel($config);

        return filled($label) ? $label : null;
    }

    public function getDefaultActions(): array
    {
        return [
            BrickAction::make(),
        ];
    }

    /**
     * Execute block commands on the current state
     *
     * @param  array<BrickCommand>  $commands
     * @return array<int, array<string, mixed>>
     */
    public function executeCommands(array $commands): array
    {
        $state = $this->getState() ?? [];

        if (! is_array($state)) {
            $state = [];
        }

        foreach ($commands as $command) {
            if ($this->isStructureLocked() && in_array($command->name, ['insertBrick', 'deleteBrick', 'moveBrick'], true)) {
                continue;
            }

            $state = match ($command->name) {
                'insertBrick' => $this->executeInsertBrick($state, $command->arguments),
                'updateBrick' => $this->executeUpdateBrick($state, $command->arguments),
                'deleteBrick' => $this->executeDeleteBrick($state, $command->arguments),
                'moveBrick' => $this->executeMoveBrick($state, $command->arguments),
                default => $state,
            };
        }

        $state = BrickTemplates::annotate($state, $this->getCachedBricks());

        $this->state($state);

        return $state;
    }

    /**
     * Bricks can still be edited, but not added, moved or removed. Given the content whose structure must be kept
     * (e.g. the saved state), a changed structure also fails validation.
     *
     * @param  array<int, mixed> | Closure | null  $comparedTo
     */
    public function lockedStructure(bool | Closure $condition = true, array | Closure | null $comparedTo = null): static
    {
        $this->isStructureLocked = $condition;
        $this->lockedStructureReference = $comparedTo;

        return $this;
    }

    public function isStructureLocked(): bool
    {
        return (bool) $this->evaluate($this->isStructureLocked);
    }

    /**
     * @return array<int, mixed>|null
     */
    public function getLockedStructureReference(): ?array
    {
        $reference = $this->evaluate($this->lockedStructureReference);

        return is_array($reference) ? $reference : null;
    }

    public function doubleClickToEdit(bool | Closure $condition = true): static
    {
        $this->shouldDblClickToEdit = $condition;

        return $this;
    }

    public function shouldDblClickToEdit(): bool
    {
        return $this->evaluate($this->shouldDblClickToEdit) ?? false;
    }

    public function previewLayout(string | Closure | null $layout): static
    {
        $this->previewLayout = $layout;

        return $this;
    }

    public function getPreviewLayout(): ?string
    {
        return $this->evaluate($this->previewLayout) ?? config('mason.preview.layout');
    }

    /**
     * The preview renders inside an iframe fed by a separate request, so the
     * bricks and layout it may use travel as a signed payload.
     */
    public function getRenderContext(): string
    {
        return RenderContext::encode($this->getFlatBricks(), $this->getPreviewLayout(), $this->getPreviewData());
    }

    /**
     * Data the bricks get in the editor preview as toHtml()'s $data, e.g. the language of the record being edited.
     * Without it they get null. It travels signed, not encrypted, so it must be JSON and hold nothing secret.
     *
     * @param  array<string, mixed> | Closure  $data
     */
    public function previewData(array | Closure $data): static
    {
        $this->previewData = $data;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreviewData(): array
    {
        $data = $this->evaluate($this->previewData);

        return is_array($data) ? $data : [];
    }

    public function defaultColorMode(string | Closure | null $mode): static
    {
        $this->defaultColorMode = $mode;

        return $this;
    }

    public function getDefaultColorMode(): string
    {
        return $this->evaluate($this->defaultColorMode) ?? 'light';
    }

    public function colorModeToggle(bool | Closure $condition = true): static
    {
        $this->showColorModeToggle = $condition;

        return $this;
    }

    public function hasColorModeToggle(): bool
    {
        return (bool) $this->evaluate($this->showColorModeToggle);
    }

    /**
     * The brick at the path, or null for the top level and paths that don't exist.
     *
     * @return array<string, mixed>|null
     */
    public function getBrickAt(string | int | null $path): ?array
    {
        $state = $this->getState();

        return is_array($state) ? BrickTree::get($state, $path) : null;
    }

    /**
     * Whether a brick may be placed in the brick at the path (null for the top level).
     */
    public function canInsertBrickInto(string | int | null $parent, string $brickId): bool
    {
        if (BrickTree::parse($parent) === []) {
            return $this->canContainBrick(null, $brickId);
        }

        $container = $this->getBrickAt($parent);

        return $container !== null && $this->canContainBrick($container['attrs']['id'] ?? null, $brickId);
    }

    /**
     * Positions are indexes at the top level, or paths like "2.0" for bricks inside container bricks.
     *
     * @param  array<int, array<string, mixed>>  $state
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    protected function executeInsertBrick(array $state, array $arguments): array
    {
        $brick = $arguments['brick'] ?? null;
        $parent = $arguments['parent'] ?? null;
        $position = $arguments['position'] ?? null;

        if (! $brick) {
            return $state;
        }

        // An inserted brick is a new one, even when it is a copy of another; a container gets its template's bricks
        // It is new, so it is at the current version of its brick, and so are the bricks inside it
        $brick = BrickTree::withNewUids(BrickMigrator::stamp(BrickTemplates::ensure([$brick], $this->getCachedBricks()), $this->getCachedBricks())[0]);

        if (BrickTree::parse($parent) !== []) {
            $container = BrickTree::get($state, $parent);

            if ($container === null || ! $this->canContainBrick($container['attrs']['id'] ?? null, (string) ($brick['attrs']['id'] ?? ''))) {
                return $state;
            }
        }

        return BrickTree::insert($state, $parent, $position === null ? null : (int) $position, $brick);
    }

    /**
     * @param  array<int, array<string, mixed>>  $state
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    protected function executeUpdateBrick(array $state, array $arguments): array
    {
        $index = $arguments['index'] ?? null;
        $brick = $arguments['brick'] ?? null;

        if ($index === null || ! $brick) {
            return $state;
        }

        $current = BrickTree::get($state, $index);

        if ($current === null) {
            return $state;
        }

        // Editing a container brick changes its settings, not the bricks inside it
        if (! array_key_exists('content', $brick) && array_key_exists('content', $current)) {
            $brick['content'] = $current['content'];
        }

        // Nor the attributes set from elsewhere, e.g. layout data its container keeps on it
        if (is_array($current['attrs'] ?? null) && is_array($brick['attrs'] ?? null)) {
            $brick['attrs'] = [...$current['attrs'], ...$brick['attrs']];
        }

        // The brick stays the same brick
        if (filled($current['attrs']['uid'] ?? null)) {
            $brick['attrs']['uid'] = $current['attrs']['uid'];
        }

        return BrickTree::update($state, $index, $brick);
    }

    /**
     * @param  array<int, array<string, mixed>>  $state
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    protected function executeDeleteBrick(array $state, array $arguments): array
    {
        $index = $arguments['index'] ?? null;

        // Bricks a template cannot do without can only be hidden
        if ($index === null || BrickTemplates::locks($state, $index, $this->getCachedBricks())['removal']) {
            return $state;
        }

        return BrickTree::remove($state, $index);
    }

    /**
     * Without a parent the brick moves among its siblings, and "to" is its index afterwards.
     *
     * @param  array<int, array<string, mixed>>  $state
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    protected function executeMoveBrick(array $state, array $arguments): array
    {
        $from = $arguments['from'] ?? null;
        $to = $arguments['to'] ?? null;

        if ($from === null || $to === null || BrickTree::get($state, $from) === null) {
            return $state;
        }

        if (BrickTemplates::locks($state, $from, $this->getCachedBricks())['movement']) {
            return $state;
        }

        $fromPath = BrickTree::parse($from);
        $parent = array_key_exists('parent', $arguments) ? $arguments['parent'] : array_slice($fromPath, 0, -1);

        if (BrickTree::parse($parent) !== []) {
            $container = BrickTree::get($state, $parent);
            $brick = BrickTree::get($state, $from);

            if ($container === null || ! $this->canContainBrick($container['attrs']['id'] ?? null, (string) ($brick['attrs']['id'] ?? ''))) {
                return $state;
            }
        }

        return BrickTree::move($state, $fromPath, $parent, (int) $to);
    }
}

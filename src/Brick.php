<?php

declare(strict_types=1);

namespace Awcodes\Mason;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

abstract class Brick
{
    abstract public static function getId(): string;

    public static function getLabel(): string
    {
        return (string) str(static::getId())
            ->kebab()
            ->replace('-', ' ')
            ->ucwords();
    }

    /**
     * Version of the brick's stored format. Raise it together with a step in getMigrations() when the format changes,
     * so that stored bricks are brought up to date (BrickMigrator).
     */
    public static function getVersion(): int
    {
        return 1;
    }

    /**
     * Steps from one version to the next, keyed by the version they start from: [1 => fn (array $brick): array => …].
     * A step gets the whole stored brick (attrs, config and the bricks inside it) and returns it changed.
     *
     * @return array<int, Closure(array<string, mixed>): array<string, mixed>>
     */
    public static function getMigrations(): array
    {
        return [];
    }

    public static function getIcon(): string | Heroicon | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public static function getTags(): array
    {
        return [];
    }

    /**
     * Distinguishes this brick from others of the same type in the editor outline.
     *
     * @param  array<string, mixed>  $config
     */
    public static function getOutlineLabel(array $config): ?string
    {
        return null;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return null;
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action->modalHidden();
    }
}

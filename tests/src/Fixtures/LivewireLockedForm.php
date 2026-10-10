<?php

declare(strict_types=1);

namespace Awcodes\Mason\Tests\Fixtures;

use Awcodes\Mason\Mason;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Workbench\App\Models\Page;

class LivewireLockedForm extends LivewireForm
{
    /**
     * The content whose structure must be kept.
     */
    public array $reference = [];

    public function mount(): void
    {
        // Filled at once: filling bricks key by key would pass through states with another structure
        $this->form->fill(['content' => $this->reference]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Page::class)
            ->schema([
                TextInput::make('title'),
                Mason::make('content')
                    ->bricks([
                        SimpleBrick::class,
                        TestBrick::class,
                        ContainerBrick::class,
                    ])
                    ->lockedStructure(comparedTo: fn (): array => $this->reference),
            ]);
    }
}

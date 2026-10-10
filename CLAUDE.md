# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mason is a block-based drag-and-drop page/document builder field for Filament (Laravel admin panel framework). It allows users to compose content using configurable "bricks" that can be added, edited, reordered, and removed.

## Development Commands

```bash
# Run all tests (refactor check, lint check, unit tests)
composer test

# Run only unit tests (with parallel execution)
composer test:unit

# Run a single test file
./vendor/bin/pest tests/src/FieldTest.php

# Run a specific test
./vendor/bin/pest --filter "test name"

# Fix code style
composer lint

# Check code style without fixing
composer test:lint

# Run Rector refactoring
composer refactor

# Check Rector without applying changes
composer test:refactor
```

## Architecture

### Core Components

- **Mason** (`src/Mason.php`): The main Filament form field component. Extends `Field` and handles state hydration/dehydration, brick commands (insert, update, delete, move), and preview layout configuration.

- **MasonEntry** (`src/MasonEntry.php`): Infolist entry component for displaying Mason content in read-only views.

- **Brick** (`src/Brick.php`): Abstract base class for all bricks. Each brick must implement:
  - `getId()`: Unique identifier
  - `getLabel()`: Display name (auto-generated from ID by default)
  - `getIcon()`: Optional icon
  - `toHtml()`: Renders the brick's HTML output
  - `configureBrickAction()`: Configures the Filament action for editing the brick

### Support Classes

- **MasonRenderer** (`src/Support/MasonRenderer.php`): Converts stored JSON content to HTML, text, or array formats
- **Faker** (`src/Support/Faker.php`): Helper for generating fake Mason content in tests
- **IframeRenderer** (`src/Support/IframeRenderer.php`): Handles rendering content within the editor iframe

### Artisan Commands

- `php artisan make:mason-brick {Name}`: Scaffolds a new brick class and view templates

## Key Patterns

### Creating Bricks

Bricks are Filament Actions with an associated view. The generator creates:
1. A PHP class in `App\Mason` namespace
2. Blade templates in `resources/views/mason/`

### Data Storage

Mason stores content as JSON arrays. Each brick entry has:
- `type`: Always "masonBrick"
- `attrs.id`: The brick's ID
- `attrs.config`: The brick's form data
- `attrs.uid`: A lasting reference to the brick (`BrickTree::ensureUids()` on load and save). It stays when the brick is edited or moved; an inserted brick, e.g. a copy, gets a new one
- `attrs.preview` and `attrs.label`: Stripped before saving (editor-only)

### Container Areas

A container brick with several areas tells them apart by an attribute of its bricks (e.g. `attrs.slot`). In `toHtmlWithChildren()`, `$children->where('slot', 'a')` gives an area's bricks, `$children->dropZone(['slot' => 'a'])` a drop zone that inserts with that attribute (passed to `BrickAction` as `attrs`), and `$children->dropTarget(['slot' => 'a'])` the attributes for the element around the area, so that bricks dropped next to its bricks or moved into it get the attribute as well. Outside the editor both are empty.

### Brick Versions

`Brick::getVersion()` and `Brick::getMigrations()` (`[fromVersion => fn (array $brick): array]`) bring stored bricks up to date. `BrickMigrator::upgrade()` runs the steps; the field runs it on load and the renderers before rendering, the version is stored as `attrs.v` (only above 1). Bricks a step adds get `BrickTree::derivedUid()`.

### Brick Context

`BrickContext` tells a brick where it is rendered without changing `toHtml()`'s signature: `uid()` and `attributes()` of the brick being rendered, and `get()` for values a container passed to the bricks inside it (`$children->with([...])`, `$child->with([...])`, or `BrickContext::with([...], fn)` around another renderer). Values reach nested containers' bricks too. The renderers render a container's bricks lazily, when it outputs them, so that its values reach them.

### Locked Structure

`->lockedStructure($condition, comparedTo: $content)` keeps bricks editable but stops adding, moving and removing them: the editor hides those controls, `executeCommands()` skips insert, move and delete, and with `comparedTo` a validation rule checks the saved structure (`BrickTree::hasSameStructure()`), as the editor deletes and moves bricks in the browser.

### Preview Data

`->previewData(array|Closure)` hands the bricks data in the editor preview as `toHtml()`'s `$data` (without it they get null, as before), e.g. the language of the record being edited. It travels in the signed `RenderContext`, so it must be JSON and nothing secret, and it should not change between Livewire renders.

### Rendering Content

```php
// Helper function
mason($content, $bricks)->toHtml()

// Full renderer API
MasonRenderer::make($content)->bricks($bricks)->toHtml()
```

## Code Style

- Uses Laravel Pint with strict rules (see `pint.json`)
- Requires `declare(strict_types=1)` in all PHP files
- PHPStan level 5 for static analysis
- Follow PSR-2 with class element ordering defined in pint.json

---
title: Container bricks
description: Bricks that hold other bricks, such as columns or a card grid.
---

# Container bricks

A container brick holds other bricks. Its own form has its settings (e.g. how many columns), and the bricks inside it are added, edited, moved and removed in the editor like any other brick: drag them in, use the drop zone's add button, or move them out again.

## Creating one

Implement `ContainsBricks`. Mason renders the bricks inside and hands them over, so that the editor can wrap each of them in its controls. The container only decides where they go:

```php
use Awcodes\Mason\Brick;
use Awcodes\Mason\Contracts\ContainsBricks;
use Awcodes\Mason\Support\BrickChildren;

class Columns extends Brick implements ContainsBricks
{
    public static function getId(): string
    {
        return 'columns';
    }

    public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
    {
        return view('mason.columns', [
            'columns' => $config['columns'] ?? 2,
            'children' => $children,
        ])->render();
    }

    /**
     * Null allows every brick of the field.
     */
    public static function getAllowedChildren(): ?array
    {
        return [Section::class, 'image'];
    }
}
```

```blade
<div class="grid" style="grid-template-columns: repeat({{ $columns }}, 1fr)">
    @foreach ($children as $child)
        <div>{{ $child }}</div>
    @endforeach

    {{ $children->dropZone() }}
</div>
```

Output `dropZone()` after the bricks. In the editor it is where bricks are dropped into the container, everywhere else it is empty. A container that wraps nothing can output `{{ $children }}`, which includes the drop zone.

Each child gives access to the stored brick:

- `$child->getId()` and `$child->getConfig()`
- `$child->getAttribute('layout.span', 1)` for attributes the container keeps on its bricks, e.g. their placement

`toHtml()` is not called for a container brick. `getAllowedChildren()` takes classes or ids; inserting or moving other bricks into the container is refused, in the editor and on the server.

## Passing values to the bricks inside

A container can hand values to the bricks inside it, e.g. a heading level or the id it is labelled by. They reach every brick inside, also those inside further containers that pass nothing on themselves:

```php
public static function toHtmlWithChildren(array $config, BrickChildren $children, ?array $data = null): ?string
{
    $level = BrickContext::get('heading_level', 2);

    return '<section>' . $children->with(['heading_level' => $level + 1])->toHtml() . '</section>';
}
```

```php
use Awcodes\Mason\Support\BrickContext;

public static function toHtml(array $config, ?array $data = null): ?string
{
    $level = min(6, BrickContext::get('heading_level', 2));

    return "<h{$level}>" . e($config['text'] ?? '') . "</h{$level}>";
}
```

`$child->with([...])` does the same for one brick. The bricks inside are rendered when the container outputs them, so they see the values it passes. Values can be objects, e.g. one that the first heading inside fills with its id, so that the container can point `aria-labelledby` at it after rendering its bricks.

For content rendered by another renderer inside a brick, e.g. shared content embedded in a page, `BrickContext::with(['key' => 'value'], fn () => mason($content, $bricks)->toHtml())` hands it values too.

## Areas

A container can have several areas, e.g. a header and a body, or slots. It tells them apart by an attribute of its bricks and gives each area its own drop zone:

```blade
@foreach (['header', 'body'] as $area)
    <div {{ $children->dropTarget(['area' => $area]) }}>
        {{ $children->where('area', $area) }}
        {{ $children->dropZone(['area' => $area]) }}
    </div>
@endforeach
```

- `where('area', 'body')` gives the bricks whose attribute has that value.
- `dropZone(['area' => 'body'])` inserts bricks with that attribute. Bricks dragged onto it are moved there and get the attribute too, also when they only change the area.
- `dropTarget(['area' => 'body'])` marks the element around the area, so that bricks dropped next to its bricks, or added with a brick's add button, get the attribute as well.

Outside the editor both render nothing. The attribute is stored on the brick (`attrs.area`) and read with `$child->getAttribute('area')`. It never replaces a brick's `id` or `config`.

## Templates and locked bricks

A container can come with bricks inside it, e.g. a heading on top of an accordion. Implement `HasBrickTemplate`:

```php
use Awcodes\Mason\Contracts\HasBrickTemplate;
use Awcodes\Mason\Support\BrickTemplate;

class Accordion extends Brick implements HasBrickTemplate
{
    public static function getTemplate(): array
    {
        return [
            BrickTemplate::make('heading', Heading::class)
                ->config(['level' => 'auto'])
                ->lockRemoval()
                ->lockMovement(),
        ];
    }

    // toHtmlWithChildren(), getAllowedChildren() …
}
```

- An inserted container gets the bricks of its template. Each stores the key of its entry (`attrs.template`).
- `lockRemoval()`: the brick cannot be removed. Instead of delete, the editor offers hide and show (`attrs.hidden`); the renderers leave hidden bricks out, the preview shows them dimmed. If the brick is missing when the content is loaded or saved, it is added again.
- `lockMovement()`: the brick cannot be moved or dragged.
- Locks follow the template in the code, never the stored content, so they cannot be forged and change with the code. Locked bricks are marked in the preview.
- A template only fills a new container. Bricks a template adds later reach existing containers only if they are locked against removal; for anything else, use a [migration](creating.md#changing-the-stored-format).

## Storage

The bricks inside a container are stored under its `content` key:

```json
{
    "type": "masonBrick",
    "attrs": {"id": "columns", "config": {"columns": 2}},
    "content": [
        {"type": "masonBrick", "attrs": {"id": "section", "config": {}}}
    ]
}
```

Editing a container changes its config and keeps the bricks inside it, as well as attributes other code keeps on it.

## Paths

Bricks are addressed by a path of indexes: `2` is the third brick at the top level, `2.0` the first brick inside it. `BrickCommand` takes paths wherever it took an index, and `insertBrick()` and `moveBrick()` take the path of the container to insert into or move into:

```php
$component->executeCommands([
    BrickCommand::insertBrick($brick, position: 0, parent: '2'),
    BrickCommand::moveBrick('2.0', to: 1, parent: ''),
]);
```

`Awcodes\Mason\Support\BrickTree` reads and changes nested content outside the field: `get()`, `children()`, `insert()`, `update()`, `remove()`, `move()`, and `walk()` and `map()` to visit every brick, parents before their children.

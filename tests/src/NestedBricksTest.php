<?php

declare(strict_types=1);

use Awcodes\Mason\Mason;
use Awcodes\Mason\Support\BrickCommand;
use Awcodes\Mason\Support\BrickContext;
use Awcodes\Mason\Support\BrickMigrator;
use Awcodes\Mason\Support\BrickTree;
use Awcodes\Mason\Support\Faker;
use Awcodes\Mason\Support\IframeEntryRenderer;
use Awcodes\Mason\Support\IframeRenderer;
use Awcodes\Mason\Support\MasonRenderer;
use Awcodes\Mason\Tests\Fixtures\AreaContainerBrick;
use Awcodes\Mason\Tests\Fixtures\ContainerBrick;
use Awcodes\Mason\Tests\Fixtures\ContextBrick;
use Awcodes\Mason\Tests\Fixtures\LevelContainerBrick;
use Awcodes\Mason\Tests\Fixtures\LivewireLockedForm;
use Awcodes\Mason\Tests\Fixtures\LivewireNestedForm;
use Awcodes\Mason\Tests\Fixtures\LivewireTemplateForm;
use Awcodes\Mason\Tests\Fixtures\RestrictedContainerBrick;
use Awcodes\Mason\Tests\Fixtures\SimpleBrick;
use Awcodes\Mason\Tests\Fixtures\TemplateContainerBrick;
use Awcodes\Mason\Tests\Fixtures\TestBrick;
use Awcodes\Mason\Tests\Fixtures\UnmigratedBrick;
use Awcodes\Mason\Tests\Fixtures\VersionedBrick;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Workbench\App\Models\Page;

function nestedBrick(string $id, array $config = [], ?array $content = null, array $attrs = []): array
{
    $brick = [
        'type' => 'masonBrick',
        'attrs' => ['id' => $id, 'config' => $config, ...$attrs],
    ];

    if ($content !== null) {
        $brick['content'] = $content;
    }

    return $brick;
}

function nestedBricks(): array
{
    return [SimpleBrick::class, TestBrick::class, ContainerBrick::class, RestrictedContainerBrick::class];
}

function nestedMason(array $state): Mason
{
    $livewire = Livewire::test(LivewireNestedForm::class)
        ->fillForm(['content' => $state])
        ->instance();

    return $livewire->form->getComponent(fn ($component): bool => $component instanceof Mason);
}

describe('BrickTree', function () {
    $tree = fn (): array => [
        nestedBrick('test-brick', ['title' => 'A']),
        nestedBrick('container-brick', [], [
            nestedBrick('test-brick', ['title' => 'B']),
            nestedBrick('container-brick', [], [
                nestedBrick('test-brick', ['title' => 'C']),
            ]),
        ]),
        nestedBrick('test-brick', ['title' => 'D']),
    ];

    it('gets bricks by path', function () use ($tree) {
        expect(BrickTree::get($tree(), '0')['attrs']['config']['title'])->toBe('A')
            ->and(BrickTree::get($tree(), '1.0')['attrs']['config']['title'])->toBe('B')
            ->and(BrickTree::get($tree(), '1.1.0')['attrs']['config']['title'])->toBe('C')
            ->and(BrickTree::get($tree(), 2)['attrs']['config']['title'])->toBe('D')
            ->and(BrickTree::get($tree(), '1.5'))->toBeNull()
            ->and(BrickTree::get($tree(), ''))->toBeNull();
    });

    it('inserts into a container', function () use ($tree) {
        $blocks = BrickTree::insert($tree(), '1.1', 0, nestedBrick('test-brick', ['title' => 'New']));

        expect(BrickTree::get($blocks, '1.1.0')['attrs']['config']['title'])->toBe('New')
            ->and(BrickTree::get($blocks, '1.1.1')['attrs']['config']['title'])->toBe('C');
    });

    it('inserts into an empty container', function () {
        $blocks = BrickTree::insert([nestedBrick('container-brick')], '0', null, nestedBrick('test-brick'));

        expect($blocks[0]['content'])->toHaveCount(1);
    });

    it('removes nested bricks', function () use ($tree) {
        $blocks = BrickTree::remove($tree(), '1.0');

        expect($blocks[1]['content'])->toHaveCount(1)
            ->and(BrickTree::get($blocks, '1.0.0')['attrs']['config']['title'])->toBe('C');
    });

    it('moves a brick into a container that follows it', function () use ($tree) {
        // Taking "A" out shifts the container from 1 to 0
        $blocks = BrickTree::move($tree(), '0', '1.1', 1);

        expect($blocks)->toHaveCount(2)
            ->and(BrickTree::get($blocks, '0.1.1')['attrs']['config']['title'])->toBe('A');
    });

    it('moves a nested brick to the top level', function () use ($tree) {
        $blocks = BrickTree::move($tree(), '1.1.0', '', 0);

        expect($blocks)->toHaveCount(4)
            ->and($blocks[0]['attrs']['config']['title'])->toBe('C')
            ->and($blocks[2]['content'][1]['content'])->toBe([]);
    });

    it('does not move a container into itself', function () use ($tree) {
        expect(BrickTree::move($tree(), '1', '1.1', 0))->toBe($tree());
    });

    it('walks every brick with its path', function () use ($tree) {
        $paths = [];

        BrickTree::walk($tree(), function (array $block, string $path) use (&$paths): void {
            $paths[] = $path;
        });

        expect($paths)->toBe(['0', '1', '1.0', '1.1', '1.1.0', '2']);
    });
});

describe('container bricks', function () {
    it('renders the bricks inside a container', function () {
        $html = MasonRenderer::make([
            nestedBrick('container-brick', ['title' => 'Outer'], [
                nestedBrick('simple-brick', attrs: ['layout' => ['span' => 2]]),
                nestedBrick('container-brick', ['title' => 'Inner'], [
                    nestedBrick('test-brick', ['title' => 'Deep']),
                ]),
            ]),
        ])->bricks(nestedBricks())->toUnsafeHtml();

        expect($html)
            ->toContain('<div class="container" data-title="Outer"><div class="cell" data-span="2"><div class="simple-brick">')
            ->toContain('data-title="Inner"><div class="cell" data-span="1"><div class="test-brick"><h2>Deep</h2>')
            ->not->toContain('mason-block')
            ->not->toContain('mason-drop-zone');
    });

    it('leaves out bricks the container does not accept', function () {
        $html = MasonRenderer::make([
            nestedBrick('restricted-container-brick', [], [
                nestedBrick('simple-brick'),
                nestedBrick('test-brick', ['title' => 'Not allowed']),
            ]),
        ])->bricks(nestedBricks())->toUnsafeHtml();

        expect($html)->toContain('simple-brick')
            ->not->toContain('Not allowed');
    });

    it('renders nested bricks as text', function () {
        $text = Faker::make()
            ->brick('container-brick', [], Faker::make()->brick('test-brick', ['title' => 'Nested title']))
            ->asJson();

        expect(MasonRenderer::make($text)->bricks(nestedBricks())->toText())->toContain('Nested title');
    });

    it('wraps nested bricks in editor controls in the preview', function () {
        $html = IframeRenderer::make([
            nestedBrick('simple-brick'),
            nestedBrick('container-brick', [], [
                nestedBrick('test-brick', ['title' => 'Nested']),
            ]),
        ])->bricks(nestedBricks())->toHtml();

        expect($html)
            ->toContain('data-block-path="1.0"')
            ->toContain('data-drop-parent="1"')
            ->toContain('data-accepts="simple-brick,test-brick,container-brick,restricted-container-brick"')
            ->toContain('<h2>Nested</h2>');
    });

    it('lists only allowed bricks as accepted', function () {
        $html = IframeRenderer::make([
            nestedBrick('restricted-container-brick'),
        ])->bricks(nestedBricks())->toHtml();

        expect($html)->toContain('data-accepts="simple-brick"')
            ->toContain('mason-drop-zone-empty');
    });

    it('renders nested bricks in the entry', function () {
        $html = IframeEntryRenderer::make([
            nestedBrick('container-brick', [], [nestedBrick('simple-brick')]),
        ])->bricks(nestedBricks())->toHtml();

        expect($html)->toContain('<div class="cell" data-span="1"><div class="simple-brick">')
            ->not->toContain('mason-drop-zone');
    });
});

describe('Mason commands on nested bricks', function () {
    it('inserts into a container', function () {
        $state = nestedMason([nestedBrick('container-brick')])->executeCommands([
            BrickCommand::insertBrick(nestedBrick('simple-brick'), 0, '0'),
        ]);

        expect($state[0]['content'][0]['attrs']['id'])->toBe('simple-brick');
    });

    it('refuses bricks a container does not accept', function () {
        $state = nestedMason([nestedBrick('restricted-container-brick')])->executeCommands([
            BrickCommand::insertBrick(nestedBrick('test-brick'), 0, '0'),
        ]);

        expect($state[0])->not->toHaveKey('content');
    });

    it('refuses to insert into a brick that is not a container', function () {
        $state = nestedMason([nestedBrick('simple-brick')])->executeCommands([
            BrickCommand::insertBrick(nestedBrick('simple-brick'), 0, '0'),
        ]);

        expect($state[0])->not->toHaveKey('content');
    });

    it('keeps the bricks inside a container when it is edited', function () {
        $state = nestedMason([
            nestedBrick('container-brick', ['title' => 'Old'], [nestedBrick('simple-brick')]),
        ])->executeCommands([
            BrickCommand::updateBrick(0, nestedBrick('container-brick', ['title' => 'New'])),
        ]);

        expect($state[0]['attrs']['config']['title'])->toBe('New')
            ->and($state[0]['content'])->toHaveCount(1);
    });

    it('keeps attributes like layout data when a brick is edited', function () {
        $state = nestedMason([
            nestedBrick('container-brick', [], [
                nestedBrick('test-brick', ['title' => 'Old'], attrs: ['layout' => ['lg' => ['col' => 2]]]),
            ]),
        ])->executeCommands([
            BrickCommand::updateBrick('0.0', nestedBrick('test-brick', ['title' => 'New'])),
        ]);

        expect($state[0]['content'][0]['attrs'])
            ->config->toBe(['title' => 'New'])
            ->layout->toBe(['lg' => ['col' => 2]]);
    });

    it('updates and deletes nested bricks by path', function () {
        $mason = nestedMason([
            nestedBrick('container-brick', [], [
                nestedBrick('test-brick', ['title' => 'Old']),
                nestedBrick('simple-brick'),
            ]),
        ]);

        $state = $mason->executeCommands([
            BrickCommand::updateBrick('0.0', nestedBrick('test-brick', ['title' => 'New'])),
            BrickCommand::deleteBrick('0.1'),
        ]);

        expect($state[0]['content'])->toHaveCount(1)
            ->and($state[0]['content'][0]['attrs']['config']['title'])->toBe('New');
    });

    it('moves a brick into a container', function () {
        $state = nestedMason([
            nestedBrick('simple-brick'),
            nestedBrick('container-brick', [], []),
        ])->executeCommands([
            BrickCommand::moveBrick(0, 0, '1'),
        ]);

        expect($state)->toHaveCount(1)
            ->and($state[0]['content'][0]['attrs']['id'])->toBe('simple-brick');
    });

    it('strips editor attributes of nested bricks on save', function () {
        Livewire::test(LivewireNestedForm::class)
            ->fillForm([
                'title' => 'Nested',
                'content' => [
                    nestedBrick('container-brick', [], [
                        nestedBrick('simple-brick', attrs: ['preview' => 'abc', 'label' => 'Simple', 'outlineLabel' => 'x']),
                    ], ['label' => 'Container']),
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $content = Page::query()->first()->content;

        expect($content[0]['attrs'])->not->toHaveKey('label')
            ->and(withoutUids($content)[0]['content'][0]['attrs'])->toBe(['id' => 'simple-brick', 'config' => []]);
    });
});

describe('brick uids', function () {
    it('gives every brick a uid when the content is loaded', function () {
        $mason = nestedMason([
            nestedBrick('container-brick', [], [nestedBrick('simple-brick')]),
            nestedBrick('test-brick', attrs: ['uid' => 'kept']),
        ]);

        $state = $mason->callAfterStateHydrated()->getState();

        expect($state[0]['attrs']['uid'])->toBeString()->not->toBeEmpty()
            ->and($state[0]['content'][0]['attrs']['uid'])->toBeString()->not->toBe($state[0]['attrs']['uid'])
            ->and($state[1]['attrs']['uid'])->toBe('kept');
    });

    it('gives the second of two bricks with the same uid a new one', function () {
        $blocks = BrickTree::ensureUids([
            nestedBrick('test-brick', attrs: ['uid' => 'same']),
            nestedBrick('container-brick', [], [nestedBrick('test-brick', attrs: ['uid' => 'same'])]),
        ]);

        expect($blocks[0]['attrs']['uid'])->toBe('same')
            ->and($blocks[1]['content'][0]['attrs']['uid'])->not->toBe('same');
    });

    it('keeps the uid when a brick is edited or moved', function () {
        $mason = nestedMason([
            nestedBrick('test-brick', ['title' => 'Old'], attrs: ['uid' => 'a']),
            nestedBrick('container-brick', [], [], ['uid' => 'b']),
        ]);

        $state = $mason->executeCommands([
            BrickCommand::updateBrick(0, nestedBrick('test-brick', ['title' => 'New'], attrs: ['uid' => 'other'])),
            BrickCommand::moveBrick(0, 0, '1'),
        ]);

        expect($state[0]['attrs']['uid'])->toBe('b')
            ->and($state[0]['content'][0]['attrs'])
            ->uid->toBe('a')
            ->config->toBe(['title' => 'New']);
    });

    it('gives an inserted brick and the bricks inside it new uids, also when it is a copy', function () {
        $mason = nestedMason([
            nestedBrick('container-brick', [], [nestedBrick('simple-brick', attrs: ['uid' => 'child'])], ['uid' => 'parent']),
        ]);

        $state = $mason->executeCommands([
            BrickCommand::insertBrick($mason->getState()[0], 1),
        ]);

        expect($state[1]['attrs']['uid'])->not->toBe('parent')
            ->and($state[1]['content'][0]['attrs']['uid'])->not->toBe('child')
            ->and($state[0]['attrs']['uid'])->toBe('parent');
    });

    it('saves the uids', function () {
        Livewire::test(LivewireNestedForm::class)
            ->fillForm([
                'title' => 'Nested',
                'content' => [nestedBrick('simple-brick', attrs: ['uid' => 'saved'])],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Page::query()->first()->content[0]['attrs']['uid'])->toBe('saved');
    });
});

describe('locked structure', function () {
    $content = fn (): array => [
        nestedBrick('test-brick', ['title' => 'A'], attrs: ['uid' => 'a']),
        nestedBrick('container-brick', [], [nestedBrick('simple-brick', attrs: ['uid' => 'c'])], ['uid' => 'b']),
    ];

    it('compares structures by brick ids and uids', function () use ($content) {
        $changed = $content();
        $changed[0]['attrs']['config']['title'] = 'Changed';
        $withoutUid = $content();
        unset($withoutUid[1]['content'][0]['attrs']['uid']);

        expect(BrickTree::hasSameStructure($content(), $changed))->toBeTrue()
            ->and(BrickTree::hasSameStructure($content(), $withoutUid))->toBeTrue()
            ->and(BrickTree::hasSameStructure($content(), array_reverse($content())))->toBeFalse()
            ->and(BrickTree::hasSameStructure($content(), [$content()[0]]))->toBeFalse()
            ->and(BrickTree::hasSameStructure($content(), BrickTree::insert($content(), '1', 0, nestedBrick('simple-brick'))))->toBeFalse();

        $otherUid = $content();
        $otherUid[0]['attrs']['uid'] = 'x';

        expect(BrickTree::hasSameStructure($content(), $otherUid))->toBeFalse();
    });

    it('edits bricks but does not add, move or remove them', function () use ($content) {
        $mason = nestedMason($content())->lockedStructure();

        $state = $mason->executeCommands([
            BrickCommand::insertBrick(nestedBrick('simple-brick'), 0),
            BrickCommand::moveBrick(0, 0, '1'),
            BrickCommand::deleteBrick('1.0'),
            BrickCommand::updateBrick(0, nestedBrick('test-brick', ['title' => 'New'])),
        ]);

        expect(withoutUids($state))->toBe(withoutUids([
            nestedBrick('test-brick', ['title' => 'New']),
            ...array_slice($content(), 1),
        ]));
    });

    it('saves edited bricks', function () use ($content) {
        Livewire::test(LivewireLockedForm::class, ['reference' => $content()])
            ->set('data.title', 'Locked')
            ->set('data.content.0.attrs.config.title', 'Changed')
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Page::query()->first()->content[0]['attrs']['config']['title'])->toBe('Changed');
    });

    it('refuses a changed structure on save', function () use ($content) {
        Livewire::test(LivewireLockedForm::class, ['reference' => $content()])
            ->set('data.title', 'Locked')
            ->set('data.content', array_reverse($content()))
            ->call('create')
            ->assertHasFormErrors(['content']);

        expect(Page::query()->count())->toBe(0);
    });
});

describe('container areas', function () {
    $content = fn (): array => [
        nestedBrick('area-container-brick', [], [
            nestedBrick('simple-brick', attrs: ['area' => 'a']),
        ]),
    ];

    it('renders an area with its bricks, a drop target and a drop zone that inserts into it', function () use ($content) {
        $html = IframeRenderer::make($content())->bricks([SimpleBrick::class, AreaContainerBrick::class])->toHtml();
        $attrs = e(json_encode(['area' => 'a']));

        expect($html)
            ->toContain('<section class="area-a" data-drop-target data-drop-parent="0" data-drop-attrs="' . $attrs . '">')
            ->toContain('data-drop-attrs="' . $attrs . '"')
            ->toMatch('/class="area-a".*simple-brick.*class="area-b"/s')
            // Only the empty area shows the placeholder
            ->and(str($html)->betweenFirst('class="area-a"', '</section>')->toString())->not->toContain('mason-drop-zone-empty')
            ->and(str($html)->betweenFirst('class="area-b"', '</section>')->toString())->toContain('mason-drop-zone-empty');
    });

    it('renders no editor markup outside the editor', function () use ($content) {
        $html = MasonRenderer::make($content())->bricks([SimpleBrick::class, AreaContainerBrick::class])->toHtml();

        expect($html)->toMatch('/<section class="area-a"\s*><div class="simple-brick">/')
            ->not->toContain('data-drop');
    });

    it('inserts a brick with the attributes of the drop zone, but not over its own', function () {
        Livewire::test(LivewireNestedForm::class)
            ->fillForm(['content' => [nestedBrick('container-brick', [], [], ['uid' => 'c'])]])
            ->callAction(
                TestAction::make('handleBrick')->schemaComponent('content')->arguments([
                    'id' => 'simple-brick',
                    'mode' => 'insert',
                    'parent' => '0',
                    'position' => 0,
                    'attrs' => ['area' => 'b', 'id' => 'test-brick', 'uid' => 'stolen'],
                ]),
            )
            ->assertSchemaStateSet(fn (array $state) => expect($state['content'][0]['content'][0]['attrs'])
                ->area->toBe('b')
                ->id->toBe('simple-brick')
                ->uid->not->toBe('stolen'));
    });
});

describe('brick context', function () {
    $content = fn (): array => [
        nestedBrick('context-brick', attrs: ['uid' => 'top']),
        nestedBrick('level-container-brick', [], [
            nestedBrick('context-brick', attrs: ['uid' => 'inner']),
            // A container that passes nothing on keeps the values around it
            nestedBrick('container-brick', [], [
                nestedBrick('context-brick', attrs: ['uid' => 'deep']),
                nestedBrick('level-container-brick', [], [nestedBrick('context-brick', attrs: ['uid' => 'deeper'])]),
            ]),
        ], ['uid' => 'group']),
    ];
    $bricks = [ContextBrick::class, LevelContainerBrick::class, ContainerBrick::class];

    it('gives bricks their uid and the values of the containers around them', function (string $renderer, string $method) use ($content, $bricks) {
        $html = $renderer::make($content())->bricks($bricks)->{$method}();

        expect($html)
            ->toContain('data-uid="top" data-level="1"')
            ->toContain('data-uid="inner" data-level="2"')
            ->toContain('data-uid="deep" data-level="2"')
            ->toContain('data-uid="deeper" data-level="3"');
    })->with([
        'website' => [MasonRenderer::class, 'toUnsafeHtml'],
        'editor preview' => [IframeRenderer::class, 'toHtml'],
        'entry' => [IframeEntryRenderer::class, 'toHtml'],
    ]);

    it('hands values to content rendered inside a brick', function () use ($bricks) {
        $html = BrickContext::with(['level' => 5], fn () => MasonRenderer::make([nestedBrick('context-brick', attrs: ['uid' => 'x'])])->bricks($bricks)->toUnsafeHtml());

        expect($html)->toContain('data-level="5"')
            ->and(BrickContext::get('level'))->toBeNull()
            ->and(BrickContext::uid())->toBeNull();
    });

    it('is empty again after a brick fails to render', function () {
        try {
            BrickContext::with(['level' => 2], fn () => throw new RuntimeException('failed'));
        } catch (RuntimeException) {
        }

        expect(BrickContext::values())->toBe([]);
    });
});

describe('brick versions', function () {
    $old = fn (): array => nestedBrick('versioned-brick', ['title' => 'Old', 'intro' => 'Hello'], attrs: ['uid' => 'v']);
    $bricks = fn (): array => ['versioned-brick' => VersionedBrick::class, 'test-brick' => TestBrick::class, 'container-brick' => ContainerBrick::class];

    it('runs every step from the stored version to the current one', function () use ($old, $bricks) {
        $brick = BrickMigrator::upgrade([$old()], $bricks())[0];

        expect($brick['attrs'])
            ->v->toBe(3)
            ->config->toBe(['heading' => 'Old'])
            ->and($brick['content'][0]['attrs'])
            ->uid->toBe(BrickTree::derivedUid('v', 'intro'))
            ->config->toBe(['title' => 'Hello']);
    });

    it('starts from the stored version and leaves current bricks alone', function () use ($bricks) {
        $atTwo = nestedBrick('versioned-brick', ['heading' => 'Kept', 'intro' => 'Hi'], attrs: ['uid' => 'v', 'v' => 2]);
        $current = nestedBrick('versioned-brick', ['heading' => 'Current'], [], ['v' => 3]);
        $plain = nestedBrick('test-brick', ['title' => 'Plain']);

        [$fromTwo, $same, $untouched] = BrickMigrator::upgrade([$atTwo, $current, $plain], $bricks());

        expect($fromTwo['attrs']['config'])->toBe(['heading' => 'Kept'])
            ->and($same)->toBe($current)
            // Bricks at version 1 get no version stored
            ->and($untouched)->toBe($plain);
    });

    it('upgrades bricks inside containers', function () use ($old, $bricks) {
        $blocks = BrickMigrator::upgrade([nestedBrick('container-brick', [], [$old()])], $bricks());

        expect($blocks[0]['content'][0]['attrs']['config'])->toBe(['heading' => 'Old']);
    });

    it('fails loudly when a step is missing', function () {
        BrickMigrator::upgradeBrick(nestedBrick('unmigrated-brick'), UnmigratedBrick::class);
    })->throws(LogicException::class, 'has no migration from version 1');

    it('derives the same uid for the same parent and name', function () {
        expect(BrickTree::derivedUid('a', 'intro'))->toBe(BrickTree::derivedUid('a', 'intro'))
            ->not->toBe(BrickTree::derivedUid('b', 'intro'))
            ->not->toBe(BrickTree::derivedUid('a', 'other'))
            ->toHaveLength(26);
    });

    it('marks inserted bricks with the current version, so that they are not upgraded again', function () use ($bricks) {
        $mason = nestedMason([])->bricks([VersionedBrick::class, TestBrick::class]);
        $state = $mason->executeCommands([BrickCommand::insertBrick(nestedBrick('versioned-brick', ['heading' => 'New']), 0)]);

        expect($state[0]['attrs']['v'])->toBe(3)
            ->and(BrickMigrator::upgrade($state, $bricks())[0]['content'] ?? [])->toBe([]);
    });

    it('upgrades content when the field loads it and when it is rendered', function () use ($old) {
        $mason = nestedMason([$old()])->bricks([VersionedBrick::class, TestBrick::class]);
        $state = $mason->callAfterStateHydrated()->getState();

        expect($state[0]['attrs']['v'])->toBe(3)
            ->and(MasonRenderer::make([$old()])->bricks([VersionedBrick::class, TestBrick::class])->toUnsafeHtml())
            ->toContain('data-heading="Old"')
            ->toContain('Hello')
            ->and(IframeRenderer::make([$old()])->bricks([VersionedBrick::class, TestBrick::class])->toHtml())
            ->toContain('data-heading="Old"');
    });
});

describe('brick templates', function () {
    $bricks = [TemplateContainerBrick::class, TestBrick::class, SimpleBrick::class];
    $container = fn (?array $content = null): array => nestedBrick('template-container-brick', [], $content, ['uid' => 'box']);
    $intro = fn (array $attrs = []): array => nestedBrick('test-brick', ['title' => 'Mine'], attrs: ['template' => 'intro', 'uid' => 'i', ...$attrs]);
    $extra = fn (): array => nestedBrick('simple-brick', attrs: ['template' => 'extra', 'uid' => 'e']);
    $mason = fn (array $state) => nestedMason($state)->bricks($bricks);

    it('gives an inserted container the bricks of its template', function () use ($mason) {
        $state = $mason([])->executeCommands([BrickCommand::insertBrick(nestedBrick('template-container-brick'), 0)]);

        expect(array_map(fn (array $child) => $child['attrs']['template'], $state[0]['content']))->toBe(['intro', 'extra'])
            ->and($state[0]['content'][0]['attrs']['config'])->toBe(['title' => 'Intro'])
            ->and($state[0]['content'][0]['attrs']['uid'])->toBeString()
            ->and($state[0]['content'][0]['attrs']['lock'])->toBe(['removal' => true, 'movement' => true])
            ->and($state[0]['content'][1]['attrs'])->not->toHaveKey('lock');
    });

    it('refuses to remove or move a locked brick, but not the others', function () use ($mason, $container, $intro, $extra) {
        $state = $mason([$container([$intro(), $extra()])])->executeCommands([
            BrickCommand::deleteBrick('0.0'),
            BrickCommand::moveBrick('0.0', 2, '0'),
            BrickCommand::deleteBrick('0.1'),
        ]);

        expect($state[0]['content'])->toHaveCount(1)
            ->and($state[0]['content'][0]['attrs']['template'])->toBe('intro');
    });

    it('takes the locks from the code, not from the stored brick', function () use ($mason, $container) {
        // A forged lock on a free brick protects nothing
        $forged = nestedBrick('simple-brick', attrs: ['template' => 'extra', 'uid' => 'e', 'lock' => ['removal' => true, 'movement' => true]]);

        $state = $mason([$container([$forged])])->executeCommands([BrickCommand::deleteBrick('0.0')]);

        expect($state[0]['content'] ?? [])->toBe([]);
    });

    it('adds a locked brick back when it is missing, with a uid derived from the container', function () use ($mason, $container, $extra) {
        $state = $mason([$container([$extra()])])->callAfterStateHydrated()->getState();

        expect(array_map(fn (array $child) => $child['attrs']['template'], $state[0]['content']))->toBe(['intro', 'extra'])
            ->and($state[0]['content'][0]['attrs']['uid'])->toBe(BrickTree::derivedUid('box', 'template:intro'));
    });

    it('saves without the editor locks and with locked bricks the browser removed', function () use ($container, $extra) {
        Livewire::test(LivewireTemplateForm::class)
            ->fillForm(['title' => 'Templates', 'content' => [$container([$extra()])]])
            ->call('create')
            ->assertHasNoFormErrors();

        $content = Page::query()->first()->content[0]['content'];

        expect(array_map(fn (array $child) => $child['attrs']['template'], $content))->toBe(['intro', 'extra'])
            ->and($content[0]['attrs'])->not->toHaveKey('lock');
    });

    it('keeps one brick per template entry; a second one becomes an ordinary brick', function () use ($mason, $container, $intro) {
        $state = $mason([$container([$intro(), $intro(['uid' => 'i2'])])])->callAfterStateHydrated()->getState();

        expect($state[0]['content'])->toHaveCount(2)
            ->and($state[0]['content'][0]['attrs']['template'])->toBe('intro')
            ->and($state[0]['content'][1]['attrs'])->not->toHaveKey('template')
            ->and($state[0]['content'][1]['attrs'])->not->toHaveKey('lock');
    });

    it('leaves hidden bricks out on the website and marks them in the preview', function () use ($bricks, $container, $intro, $extra) {
        $content = [$container([$intro(['hidden' => true]), $extra()])];

        $website = MasonRenderer::make($content)->bricks($bricks)->toUnsafeHtml();
        $preview = IframeRenderer::make($content)->bricks($bricks)->toHtml();
        $locked = str($preview)->betweenFirst('data-block-path="0.0"', 'mason-block-content')->toString();
        $free = str($preview)->betweenFirst('data-block-path="0.1"', 'mason-block-content')->toString();

        expect($website)->not->toContain('Mine')
            ->and($preview)->toContain('Mine')
            ->and($locked)
            ->toContain('data-template="intro"')
            ->toContain('data-hidden')
            ->toContain('data-movement-locked')
            ->toContain('data-action="toggle-hidden"')
            ->not->toContain('data-action="delete"')
            ->not->toContain('data-action="move-up"')
            ->and($free)
            ->toContain('data-action="delete"')
            ->toContain('data-action="move-up"');
    });
});

// The add line at the top level is covered by IframeRendererTest
describe('add line in containers', function () {
    it('gives the drop zones of containers the same add button, with the placeholder when empty', function () {
        $html = IframeRenderer::make([
            nestedBrick('container-brick', [], [nestedBrick('simple-brick')]),
            nestedBrick('container-brick', [], []),
        ])->bricks([SimpleBrick::class, ContainerBrick::class])->toHtml();

        $filled = str($html)->betweenFirst('data-drop-parent="0"', '</div>')->toString();
        $empty = str($html)->betweenFirst('data-drop-parent="1"', '</div>')->toString();

        expect($filled)->toContain('class="mason-inserter"')->not->toContain('mason-drop-zone-placeholder')
            ->and($empty)->toContain('class="mason-inserter"')->toContain('mason-drop-zone-placeholder');
    });
});

describe('details in the preview', function () {
    it('marks bricks with their uid, so that open details are remembered per brick', function () {
        $html = IframeRenderer::make([nestedBrick('simple-brick', attrs: ['uid' => 'abc'])])->bricks([SimpleBrick::class])->toHtml();

        expect($html)->toContain('data-uid="abc"')
            ->toContain("detailsStorageKey = 'mason-preview-details'");
    });
});

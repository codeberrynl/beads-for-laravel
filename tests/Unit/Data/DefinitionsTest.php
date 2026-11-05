<?php

declare(strict_types=1);

use Codeberry\Beads\Data\IssueTypeDefinition;
use Codeberry\Beads\Data\StatusCatalog;
use Codeberry\Beads\Data\StatusDefinition;

it('maps the statuses fixture, including custom statuses', function () {
    $statuses = collect(StatusDefinition::listFrom(fixtureJson('statuses.json')))->keyBy('name');

    expect($statuses->keys()->all())->toContain('open', 'in_progress', 'blocked', 'deferred', 'closed', 'pinned', 'hooked', 'in_review', 'on_hold')
        ->and($statuses['open']->category)->toBe('active')
        ->and($statuses['open']->builtIn)->toBeTrue()
        ->and($statuses['open']->icon)->not->toBeNull()
        ->and($statuses['in_review']->category)->toBe('wip')
        ->and($statuses['in_review']->builtIn)->toBeFalse()
        ->and($statuses['in_review']->icon)->toBeNull()
        ->and($statuses['on_hold']->category)->toBe('frozen');
});

it('maps status definitions tolerantly', function () {
    $statuses = StatusDefinition::listFrom([
        ['name' => 'open', 'category' => 'ACTIVE', 'extra' => 1],
        ['category' => 'wip'],
        'qa:wip',
        'legacy',
        ['name' => 'open', 'category' => 'done'],
    ]);

    expect(array_map(fn ($s) => [$s->name, $s->category], $statuses))->toBe([
        ['open', 'active'],
        ['qa', 'wip'],
        ['legacy', null],
    ]);

    expect(StatusDefinition::listFrom('nope'))->toBe([]);
});

it('maps the types fixture with core, system and custom kinds', function () {
    $types = collect(IssueTypeDefinition::listFrom(fixtureJson('types.json')))->keyBy('name');

    expect($types['task']->kind)->toBe('core')
        ->and($types['epic']->kind)->toBe('core')
        ->and($types['gate']->kind)->toBe('system')
        ->and($types['gate']->isSystem())->toBeTrue()
        ->and($types['message']->kind)->toBe('system');
});

it('maps custom types given as strings', function () {
    $types = IssueTypeDefinition::listFrom([
        'core_types' => [['name' => 'task', 'description' => 'Work']],
        'custom_types' => ['incident', ['name' => 'rfc']],
        'system_types' => [['name' => 'event']],
    ]);

    expect(array_map(fn ($t) => [$t->name, $t->kind], $types))->toBe([
        ['task', 'core'],
        ['incident', 'custom'],
        ['rfc', 'custom'],
        ['event', 'system'],
    ]);
});

it('orders statuses frozen, active, wip, blocked, done', function () {
    $catalog = new StatusCatalog(StatusDefinition::listFrom(fixtureJson('statuses.json')));

    $order = array_map(fn ($s) => $s->name, $catalog->ordered());

    expect($order)->toBe(['deferred', 'pinned', 'on_hold', 'open', 'in_progress', 'hooked', 'in_review', 'blocked', 'closed']);
});

it('knows categories, with built-in fallbacks', function () {
    $catalog = new StatusCatalog([new StatusDefinition('shipped', 'done')]);

    expect($catalog->category('shipped'))->toBe('done')
        ->and($catalog->isDone('shipped'))->toBeTrue()
        ->and($catalog->category('open'))->toBe('active')
        ->and($catalog->isActive('open'))->toBeTrue()
        ->and($catalog->category('mystery'))->toBeNull()
        ->and($catalog->rank('mystery'))->toBe(3);
});

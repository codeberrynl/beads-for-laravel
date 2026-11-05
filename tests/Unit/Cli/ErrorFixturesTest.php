<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdResult;
use Codeberry\Beads\Cli\Exceptions\BdException;

function errorFixture(string $name): BdException
{
    $data = json_decode(fixtureContents("errors/{$name}.json"), true);

    return BdException::fromResult(new BdResult($data['command'], $data['exit_code'], $data['stdout'], $data['stderr']));
}

it('maps real bd errors to codes and readable messages', function (string $fixture, string $code, string $contains) {
    $exception = errorFixture($fixture);

    expect($exception->errorCode)->toBe($code)
        ->and($exception->getMessage())->toContain($contains)
        ->and($exception->getMessage())->not->toStartWith('Error');
})->with([
    ['not-found', BdException::NOT_FOUND, 'no issues found'],
    ['close-blocked', BdException::FAILED, 'cannot close blocked issue'],
    ['update-blocked-to-closed', BdException::FAILED, 'cannot close blocked issue'],
    ['close-pinned', BdException::FAILED, 'cannot modify pinned issue'],
    ['readonly-write', BdException::FAILED, 'not allowed in read-only mode'],
]);

it('keeps the hint of a not-found error', function () {
    expect(errorFixture('not-found')->hint)->toContain('bd history');
});

it('uses the per-issue failure of a failed update', function () {
    expect(errorFixture('update-blocked-to-closed')->getMessage())
        ->toStartWith('cannot close blocked issue')
        ->not->toContain('1 of 1');
});

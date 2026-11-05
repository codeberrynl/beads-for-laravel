<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;

function fakeExecutable(string $path): string
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, "#!/bin/sh\necho 'bd version 1.3.2'\n");
    chmod($path, 0755);

    return $path;
}

it('uses the configured binary', function () {
    $configured = fakeExecutable($this->sandbox('custom').'/bd');

    expect((new BdBinary($configured, null))->path())->toBe($configured);
});

it('prefers the configured binary over auto-detection', function () {
    $configured = fakeExecutable($this->sandbox('custom').'/bd');
    $candidate = fakeExecutable($this->sandbox('candidate').'/bd');

    $binary = new BdBinary($configured, null, [$candidate]);

    expect($binary->path())->toBe($configured);
});

it('does not fall back to auto-detection when the configured binary is missing', function () {
    $candidate = fakeExecutable($this->sandbox('candidate').'/bd');

    $binary = new BdBinary($this->sandbox('custom').'/missing-bd', null, [$candidate]);

    expect($binary->path())->toBeNull()
        ->and($binary->exists())->toBeFalse();
});

it('auto-detects the first executable candidate in order', function () {
    $first = $this->sandbox('a').'/bd';
    $second = fakeExecutable($this->sandbox('b').'/bd');
    $third = fakeExecutable($this->sandbox('c').'/bd');

    $binary = new BdBinary(null, null, [$first, $second, $third]);

    expect($binary->path())->toBe($second);
});

it('skips files that are not executable', function () {
    $plain = $this->sandbox('a').'/bd';
    file_put_contents($plain, 'not executable');
    chmod($plain, 0644);

    expect((new BdBinary(null, null, [$plain]))->path())->toBeNull();
});

it('expands the home directory in candidates', function () {
    $home = $this->sandbox('home');
    $expected = fakeExecutable($home.'/.local/bin/bd');

    $binary = new BdBinary(null, $home, ['~/.local/bin/bd']);

    expect($binary->path())->toBe($expected)
        ->and($binary->searched())->toBe([$expected]);
});

it('treats an empty configured value as not configured', function () {
    $candidate = fakeExecutable($this->sandbox('candidate').'/bd');

    expect((new BdBinary('  ', null, [$candidate]))->path())->toBe($candidate);
});

it('searches the documented locations by default', function () {
    expect(BdBinary::CANDIDATES)->toBe([
        '/opt/homebrew/bin/bd',
        '/usr/local/bin/bd',
        '~/go/bin/bd',
        '~/.local/bin/bd',
    ]);
});

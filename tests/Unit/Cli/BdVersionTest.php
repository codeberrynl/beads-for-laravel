<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdVersion;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('parses the version from bd --version output', function (string $output, ?string $expected) {
    expect(BdVersion::parse($output))->toBe($expected);
})->with([
    ['bd version 1.3.2 (Homebrew)', '1.3.2'],
    ['bd version 0.30.6 (45bb3877)', '0.30.6'],
    ["bd version 1.4.0-rc.1 (dev)\n", '1.4.0-rc.1'],
    ['something else', null],
]);

it('compares against the minimum version', function (string $version, bool $supported) {
    expect(BdVersion::satisfies($version))->toBe($supported);
})->with([
    ['1.2.9', false],
    ['0.30.6', false],
    ['1.3.0', true],
    ['1.3.2', true],
    ['1.10.0', true],
    ['2.0.0', true],
]);

function versionFor(string $binaryPath, string $cacheFile): BdVersion
{
    return new BdVersion(new BdBinary($binaryPath, null), $cacheFile, '/home/test');
}

it('runs bd --version with an absolute path and an explicit environment', function () {
    Process::fake(['*' => Process::result('bd version 1.3.2 (Homebrew)')]);
    $binary = fakeExecutable($this->sandbox('bin').'/bd');

    expect(versionFor($binary, $this->sandbox('cache').'/version.json')->current())->toBe('1.3.2');

    Process::assertRan(fn (PendingProcess $process) => $process->command === [$binary, '--version']
        && $process->environment['HOME'] === '/home/test'
        && str_starts_with($process->environment['PATH'], dirname($binary).':'));
});

it('caches the version on the path and mtime of the binary', function () {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result('bd version 1.3.2 (Homebrew)'))
        ->push(Process::result('bd version 1.4.0 (Homebrew)'))]);
    $binary = fakeExecutable($this->sandbox('bin').'/bd');
    $cache = $this->sandbox('cache').'/version.json';
    touch($binary, 1_700_000_000);

    expect(versionFor($binary, $cache)->current())->toBe('1.3.2')
        ->and(versionFor($binary, $cache)->current())->toBe('1.3.2');
    Process::assertRanTimes(fn () => true, 1);

    touch($binary, 1_700_000_100);

    expect(versionFor($binary, $cache)->current())->toBe('1.4.0');
    Process::assertRanTimes(fn () => true, 2);
});

it('reports an unsupported version', function () {
    Process::fake(['*' => Process::result('bd version 1.2.9 (Homebrew)')]);
    $binary = fakeExecutable($this->sandbox('bin').'/bd');

    $version = versionFor($binary, $this->sandbox('cache').'/version.json');

    expect($version->current())->toBe('1.2.9')
        ->and($version->isSupported())->toBeFalse();
});

it('returns null when the binary is missing or fails', function () {
    Process::fake(['*' => Process::result('', 'boom', 1)]);

    expect(versionFor($this->sandbox('bin').'/missing', $this->sandbox('cache').'/a.json')->current())->toBeNull();

    $binary = fakeExecutable($this->sandbox('bin').'/bd');
    expect(versionFor($binary, $this->sandbox('cache').'/b.json')->isSupported())->toBeFalse();
});

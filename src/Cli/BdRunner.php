<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli;

use Closure;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Support\IssueId;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs bd as a separate process, serialised by the workspace lock.
 *
 * - The command is an argument array, never a shell string.
 * - String values are always passed as --flag=value, so a value that starts
 *   with "-" can never become a flag. Free text goes after "--".
 * - Every call gets --json and --sandbox; reads also get --readonly.
 * - The environment is explicit because PHP-FPM (Herd) clears it.
 */
final class BdRunner
{
    /**
     * @param  (Closure(): ?string)|null  $actor
     */
    public function __construct(
        private readonly BdBinary $binary,
        private readonly Workspace $workspace,
        private readonly WorkspaceLock $lock,
        private readonly ?Closure $actor = null,
        private readonly int $timeout = 30,
        private readonly ?string $home = null,
    ) {}

    /**
     * @param  list<string>  $command  subcommands and issue IDs
     * @param  array<string, scalar|list<scalar>|null>  $flags
     * @param  list<string>  $arguments  free-text positionals (passed after "--")
     */
    public function read(array $command, array $flags = [], array $arguments = []): BdResult
    {
        return $this->run($command, $flags, $arguments, readonly: true);
    }

    /**
     * @param  list<string>  $command  subcommands and issue IDs
     * @param  array<string, scalar|list<scalar>|null>  $flags
     * @param  list<string>  $arguments  free-text positionals (passed after "--")
     */
    public function write(array $command, array $flags = [], array $arguments = []): BdResult
    {
        return $this->run($command, $flags, $arguments, readonly: false);
    }

    /**
     * Run inside the workspace lock (re-entrant), e.g. to keep a read and a
     * write together.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function locked(Closure $callback): mixed
    {
        return $this->lock->run($callback);
    }

    public function workspace(): Workspace
    {
        return $this->workspace;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, scalar|list<scalar>|null>  $flags
     * @param  list<string>  $arguments
     */
    public function run(array $command, array $flags = [], array $arguments = [], bool $readonly = false): BdResult
    {
        $argv = $this->buildCommand($command, $flags, $arguments, $readonly);

        return $this->lock->run(function () use ($argv): BdResult {
            try {
                $process = Process::path($this->workspace->path)
                    ->env($this->environment())
                    ->timeout($this->timeout)
                    ->run($argv);
            } catch (ProcessTimedOutException) {
                throw BdException::timeout($this->timeout);
            }

            $result = new BdResult($argv, (int) $process->exitCode(), $process->output(), $process->errorOutput());

            if (! $result->successful()) {
                throw BdException::fromResult($result);
            }

            return $result;
        });
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, scalar|list<scalar>|null>  $flags
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function buildCommand(array $command, array $flags = [], array $arguments = [], bool $readonly = false): array
    {
        $binary = $this->binary->path();

        if ($binary === null) {
            throw new RuntimeException('The bd binary could not be found.');
        }

        $argv = [$binary];

        foreach ($command as $token) {
            $argv[] = IssueId::assertValid($token);
        }

        foreach ($flags as $name => $value) {
            if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
                throw new InvalidArgumentException("Invalid bd flag name [{$name}].");
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null || $item === false) {
                    continue;
                }

                $argv[] = $item === true ? "--{$name}" : "--{$name}=".(string) $item;
            }
        }

        $argv[] = '--json';
        $argv[] = '--sandbox';

        if ($readonly) {
            $argv[] = '--readonly';
        }

        if ($arguments !== []) {
            $argv[] = '--';

            foreach ($arguments as $argument) {
                $argv[] = $argument;
            }
        }

        return $argv;
    }

    /**
     * @return array<string, string|false>
     */
    public function environment(): array
    {
        $binary = $this->binary->path();
        $actor = $this->actor !== null ? ($this->actor)() : null;

        $environment = [
            'PATH' => ($binary !== null ? dirname($binary).':' : '').'/usr/bin:/bin',
            'BD_JSON_ENVELOPE' => '1',
            'BD_NON_INTERACTIVE' => '1',
            'NO_COLOR' => '1',
            // Never let an inherited variable point bd at another workspace.
            'BEADS_DIR' => false,
            'BEADS_DB' => false,
        ];

        if ($this->home !== null) {
            $environment['HOME'] = $this->home;
        }

        if ($actor !== null && $actor !== '') {
            $environment['BEADS_ACTOR'] = $actor;
        }

        return $environment;
    }
}

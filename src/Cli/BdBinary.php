<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli;

use Codeberry\Beads\Support\Environment;

/**
 * Resolves the absolute path of the bd binary.
 *
 * A configured path always wins, even when it does not exist (that is a
 * "binary missing" state, not a reason to silently use another bd).
 */
final class BdBinary
{
    /** @var list<string> */
    public const CANDIDATES = [
        '/opt/homebrew/bin/bd',
        '/usr/local/bin/bd',
        '~/go/bin/bd',
        '~/.local/bin/bd',
    ];

    private bool $resolved = false;

    private ?string $path = null;

    /**
     * @param  list<string>|null  $candidates
     */
    public function __construct(
        private readonly ?string $configured,
        private readonly ?string $home,
        private readonly ?array $candidates = null,
    ) {}

    public function path(): ?string
    {
        if (! $this->resolved) {
            $this->path = $this->resolve();
            $this->resolved = true;
        }

        return $this->path;
    }

    public function exists(): bool
    {
        return $this->path() !== null;
    }

    /**
     * The path that was looked for, for messages when nothing was found.
     *
     * @return list<string>
     */
    public function searched(): array
    {
        if ($this->isConfigured()) {
            return [Environment::expandHome((string) $this->configured, $this->home)];
        }

        return array_map(
            fn (string $candidate): string => Environment::expandHome($candidate, $this->home),
            $this->candidates ?? self::CANDIDATES,
        );
    }

    public function isConfigured(): bool
    {
        return $this->configured !== null && trim($this->configured) !== '';
    }

    private function resolve(): ?string
    {
        foreach ($this->searched() as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

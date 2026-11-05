<?php

declare(strict_types=1);

namespace Codeberry\Beads\Workspace;

use Codeberry\Beads\Support\Environment;
use Codeberry\Beads\Support\JsonFile;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Who makes the changes from the UI, and which authors count as humans.
 *
 * The actor is BEADS_ACTOR, else `git config user.name` of the workspace
 * (cached on the mtimes of the git config files). Authors not listed in
 * "humans" get an agent badge in the UI.
 */
final class Actor
{
    /** @var list<string> */
    public const GIT_CANDIDATES = ['/opt/homebrew/bin/git', '/usr/local/bin/git', '/usr/bin/git'];

    private ?string $resolved = null;

    /**
     * @param  list<string>  $humans
     * @param  list<string>|null  $gitCandidates
     */
    public function __construct(
        private readonly ?string $configured,
        private readonly array $humans,
        private readonly Workspace $workspace,
        private readonly string $cacheFile,
        private readonly ?string $home,
        private readonly ?array $gitCandidates = null,
    ) {}

    public function name(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $configured = $this->configured !== null ? trim($this->configured) : '';

        return $this->resolved = match (true) {
            $configured !== '' => $configured,
            default => $this->gitUserName() ?? Environment::userName() ?? 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    public function humans(): array
    {
        $humans = array_values(array_filter(
            array_map(fn (mixed $human): string => trim((string) $human), $this->humans),
            fn (string $human): bool => $human !== '',
        ));

        return $humans !== [] ? $humans : [$this->name()];
    }

    public function isHuman(?string $author): bool
    {
        if ($author === null || $author === '') {
            return true;
        }

        return in_array(mb_strtolower($author), array_map('mb_strtolower', $this->humans()), true);
    }

    private function gitUserName(): ?string
    {
        $git = $this->gitBinary();

        if ($git === null) {
            return null;
        }

        $key = $this->cacheKey($git);
        $cached = JsonFile::read($this->cacheFile);

        if (is_array($cached) && ($cached['key'] ?? null) === $key) {
            return is_string($cached['name'] ?? null) ? $cached['name'] : null;
        }

        $name = null;

        try {
            $result = Process::path(is_dir($this->workspace->path) ? $this->workspace->path : sys_get_temp_dir())
                ->env(array_filter([
                    'HOME' => $this->home,
                    'PATH' => dirname($git).':/usr/bin:/bin',
                ], fn (?string $value): bool => $value !== null))
                ->timeout(5)
                ->run([$git, 'config', 'user.name']);

            $output = trim($result->output());
            $name = $result->successful() && $output !== '' ? $output : null;
        } catch (Throwable) {
            $name = null;
        }

        JsonFile::write($this->cacheFile, ['key' => $key, 'name' => $name]);

        return $name;
    }

    private function gitBinary(): ?string
    {
        foreach ($this->gitCandidates ?? self::GIT_CANDIDATES as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function cacheKey(string $git): string
    {
        $files = [
            $this->workspace->path.'/.git/config',
            $this->workspace->path.'/.git',
        ];

        if ($this->home !== null) {
            $files[] = $this->home.'/.gitconfig';
            $files[] = $this->home.'/.config/git/config';
        }

        $parts = [$git, $this->workspace->path];

        foreach ($files as $file) {
            clearstatcache(true, $file);
            $parts[] = $file.'@'.(file_exists($file) ? (int) filemtime($file) : 0);
        }

        return sha1(implode('|', $parts));
    }
}

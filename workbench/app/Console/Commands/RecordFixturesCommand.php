<?php

declare(strict_types=1);

namespace Workbench\App\Console\Commands;

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\BdVersion;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Support\Environment;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Records real bd output from the demo workspace as test fixtures in
 * tests/Fixtures/bd-<version>/. Run `composer demo` first.
 */
class RecordFixturesCommand extends Command
{
    protected $signature = 'beads:record-fixtures';

    protected $description = 'Record bd output from workbench/demo as test fixtures';

    public function handle(BdBinary $binary, BdVersion $version): int
    {
        $bd = $binary->path();
        $root = WorkbenchServiceProvider::packageRoot();
        $workspace = new Workspace($root.'/workbench/demo');

        if ($bd === null || ! $workspace->exists()) {
            $this->components->error('Run `composer demo` first (and make sure bd is installed).');

            return self::FAILURE;
        }

        $bdVersion = (string) $version->current();
        $directory = $root.'/tests/Fixtures/bd-'.$bdVersion;
        $files = new Filesystem;
        $files->deleteDirectory($directory);
        $files->ensureDirectoryExists($directory.'/errors');

        $runner = new BdRunner(
            new BdBinary($bd, null),
            $workspace,
            WorkspaceLock::forWorkspace($workspace->path, sys_get_temp_dir(), 30),
            fn () => 'Jurrid',
            60,
            Environment::home(),
        );

        $raw = function (array $command, array $flags = [], bool $readonly = true) use ($runner, $workspace): array {
            $argv = $runner->buildCommand($command, $flags, [], $readonly);
            $result = Process::path($workspace->path)->env($runner->environment())->timeout(60)->run($argv);

            return [
                'command' => array_slice($argv, 1),
                'exit_code' => $result->exitCode(),
                'stdout' => $result->output(),
                'stderr' => $result->errorOutput(),
            ];
        };

        $export = $raw(['export']);
        $files->put($directory.'/export.jsonl', $export['stdout']);

        $issues = [];
        foreach (preg_split('/\R/', trim($export['stdout'])) ?: [] as $line) {
            $issue = json_decode($line, true);
            if (is_array($issue)) {
                $issues[$issue['title']] = $issue['id'];
            }
        }

        $ideal = $issues['Payment step: support iDEAL'];
        $rateLimit = $issues['Rate limiting for the public API'];

        $files->put($directory.'/show.json', $raw(['show', $ideal])['stdout']);
        $files->put($directory.'/show-multiple.json', $raw(['show', $ideal, $rateLimit])['stdout']);
        $files->put($directory.'/statuses.json', $raw(['statuses'])['stdout']);
        $files->put($directory.'/types.json', $raw(['types'])['stdout']);
        $files->put($directory.'/ready.json', $raw(['ready'], ['limit' => 0])['stdout']);
        $files->put($directory.'/blocked.json', $raw(['blocked'])['stdout']);

        $this->putJson($directory.'/errors/not-found.json', $raw(['show', 'demo-doesnotexist']));
        $this->putJson($directory.'/errors/close-blocked.json', $raw(['close', $ideal], [], false));
        $this->putJson($directory.'/errors/update-blocked-to-closed.json', $raw(['update', $ideal], ['status' => 'closed'], false));
        $this->putJson($directory.'/errors/close-pinned.json', $raw(['close', $issues['Team conventions']], [], false));
        $this->putJson($directory.'/errors/readonly-write.json', $raw(['update', $ideal], ['priority' => 3], true));

        $this->putJson($directory.'/meta.json', [
            'bd_version' => $bdVersion,
            'recorded_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'ids' => $issues,
        ]);

        $this->components->info("Fixtures recorded in {$directory}");

        return self::SUCCESS;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function putJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}

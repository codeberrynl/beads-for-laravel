<?php

declare(strict_types=1);

namespace Workbench\App\Console\Commands;

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Support\Environment;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Creates the demo workspace in workbench/demo with the real bd.
 *
 * Repeatable: the previous demo is removed first.
 */
class DemoCommand extends Command
{
    protected $signature = 'beads:demo';

    protected $description = 'Create the demo bd workspace in workbench/demo (removes the old one)';

    private BdRunner $human;

    private BdRunner $agent;

    public function handle(BdBinary $binary): int
    {
        $bd = $binary->path();

        if ($bd === null) {
            $this->components->error('bd was not found. Install it or set BEADS_BINARY.');

            return self::FAILURE;
        }

        $path = WorkbenchServiceProvider::packageRoot().'/workbench/demo';
        $files = new Filesystem;

        $this->components->task('Removing the old demo workspace', fn () => $files->deleteDirectory($path) || ! is_dir($path));
        $files->ensureDirectoryExists($path);

        $this->components->task('Initialising bd', function () use ($bd, $path): bool {
            $environment = ['HOME' => Environment::home(), 'PATH' => dirname($bd).':/usr/bin:/bin', 'BD_NON_INTERACTIVE' => '1', 'NO_COLOR' => '1'];

            Process::path($path)->env($environment)->run(['/usr/bin/git', 'init', '-q'])->throw();
            Process::path($path)->env($environment)->timeout(60)->run([$bd, 'init', '--non-interactive', '--prefix=demo', '--skip-hooks', '--quiet'])->throw();

            return true;
        });

        $workspace = new Workspace($path);
        $lock = WorkspaceLock::forWorkspace($workspace->path, sys_get_temp_dir(), 30);
        $this->human = new BdRunner(new BdBinary($bd, null), $workspace, $lock, fn () => 'Jurrid', 60, Environment::home());
        $this->agent = new BdRunner(new BdBinary($bd, null), $workspace, $lock, fn () => 'claude-agent', 60, Environment::home());

        $this->components->task('Configuring custom statuses', function (): bool {
            $this->human->write(['config', 'set'], [], ['status.custom', 'in_review:wip,on_hold:frozen']);

            return true;
        });

        $this->components->task('Creating issues', fn () => $this->seed());

        $this->newLine();
        $this->components->info("Demo workspace ready in {$path}");

        return self::SUCCESS;
    }

    private function seed(): bool
    {
        // Epics.
        $checkout = $this->create($this->human, [
            'title' => 'Checkout redesign',
            'type' => 'epic',
            'priority' => 1,
            'description' => "Make checkout faster and clearer.\n\n## Goals\n\n- Fewer steps\n- Address autocomplete\n- Local payment methods",
            'labels' => 'checkout',
        ]);
        $search = $this->create($this->human, [
            'title' => 'Search improvements',
            'type' => 'epic',
            'priority' => 2,
            'description' => 'Better relevance and speed for product search.',
            'labels' => 'search',
        ]);

        // Checkout children.
        $design = $this->create($this->human, [
            'title' => 'Design the new checkout flow',
            'type' => 'task',
            'priority' => 1,
            'parent' => $checkout,
            'assignee' => 'Jurrid',
            'labels' => 'design',
        ]);
        $autocomplete = $this->create($this->human, [
            'title' => 'Implement address autocomplete',
            'type' => 'feature',
            'priority' => 2,
            'parent' => $checkout,
            'assignee' => 'Jurrid',
            'labels' => 'frontend',
            'description' => "Use the postcode API to fill in street and city.\n\n```ts\nconst address = await lookup(postcode, number)\n```",
            'acceptance' => "- [ ] Postcode + number fill street and city\n- [ ] Manual entry stays possible",
        ]);
        $ideal = $this->create($this->human, [
            'title' => 'Payment step: support iDEAL',
            'type' => 'feature',
            'priority' => 1,
            'parent' => $checkout,
            'labels' => 'payments,backend',
            'description' => 'Add iDEAL as a payment method in the new payment step.',
            'design' => "Use the provider's hosted page; we only store the payment reference.",
        ]);
        $safari = $this->create($this->agent, [
            'title' => 'Checkout button misaligned on Safari',
            'type' => 'bug',
            'priority' => 2,
            'parent' => $checkout,
            'labels' => 'frontend',
            'description' => "Steps to reproduce:\n\n1. Open checkout in Safari 18\n2. Resize to 375px\n\nThe button overflows its container.",
        ]);

        // Search children.
        $variants = $this->create($this->agent, [
            'title' => 'Index product variants',
            'type' => 'task',
            'priority' => 2,
            'parent' => $search,
            'assignee' => 'claude-agent',
            'labels' => 'backend',
        ]);
        $typos = $this->create($this->human, [
            'title' => 'Typo tolerance for search queries',
            'type' => 'feature',
            'priority' => 3,
            'parent' => $search,
            'labels' => 'backend',
        ]);
        $flicker = $this->create($this->human, [
            'title' => 'Search results flicker on slow connections',
            'type' => 'bug',
            'priority' => 1,
            'parent' => $search,
            'assignee' => 'claude-agent',
            'labels' => 'frontend',
        ]);

        // Standalone issues.
        $sdk = $this->create($this->human, [
            'title' => 'Upgrade the payment SDK to v5',
            'type' => 'chore',
            'priority' => 0,
            'labels' => 'payments,backend',
            'description' => 'v4 is end-of-life at the end of the month.',
        ]);
        $this->create($this->human, [
            'title' => 'Migrate CI to GitHub Actions',
            'type' => 'chore',
            'priority' => 3,
            'defer' => '+14d',
        ]);
        $this->create($this->human, [
            'title' => 'Write onboarding docs',
            'type' => 'task',
            'priority' => 4,
            'description' => "# Onboarding\n\nEverything a new developer needs.\n\n## Setup\n\n1. Clone the repo\n2. Run `composer setup`\n3. Open the app\n\n> Ask in #dev when you get stuck.\n\nSee [the wiki](https://example.com/wiki) for more.",
        ]);
        $this->create($this->agent, [
            'title' => 'Research: caching strategy for product pages',
            'type' => 'spike',
            'priority' => 3,
            'description' => "Findings so far:\n\n<details>\n<summary>Benchmarks</summary>\n\n| Strategy | p95 |\n|---|---|\n| none | 420ms |\n| full page | 35ms |\n\n</details>\n\nRecommendation: full-page cache with tag invalidation.",
        ]);
        $rateLimit = $this->create($this->human, [
            'title' => 'Rate limiting for the public API',
            'type' => 'feature',
            'priority' => 2,
            'status' => 'blocked',
            'labels' => 'backend,api',
            'notes' => 'Waiting for a decision on the limits per plan.',
        ]);
        $this->create($this->human, [
            'title' => 'Dark mode for the admin',
            'type' => 'feature',
            'priority' => 3,
            'status' => 'on_hold',
            'labels' => 'frontend',
        ]);
        $this->create($this->human, [
            'title' => 'Team conventions',
            'type' => 'decision',
            'priority' => 4,
            'status' => 'pinned',
            'description' => 'Squash merges, conventional commits, reviews within one day.',
        ]);
        $flaky = $this->create($this->agent, [
            'title' => 'Fix flaky test in OrderTest',
            'type' => 'bug',
            'priority' => 2,
            'labels' => 'tests',
        ]);

        // Statuses.
        $this->human->write(['update', $autocomplete], ['status' => 'in_progress']);
        $this->agent->write(['update', $variants], ['status' => 'in_review']);

        // Relations.
        $this->human->write(['dep', 'add', $ideal, $sdk], ['type' => 'blocks']);
        $this->human->write(['dep', 'add', $rateLimit, $sdk], ['type' => 'blocks']);
        $this->human->write(['dep', 'relate', $typos, $variants]);
        $this->human->write(['dep', 'relate', $flicker, $safari]);

        // Comments.
        $this->human->write(['comments', 'add', $ideal], [], ['Do we also need Bancontact for Belgium?']);
        $this->agent->write(['comments', 'add', $ideal], [], ["Bancontact uses the same provider API, so it is a small addition. I would track it as a follow-up.\n\n- Same hosted page\n- Different method code"]);
        $this->agent->write(['comments', 'add', $flicker], [], ['Reproduced with network throttling (Slow 3G). The skeleton is replaced before the results arrive.']);
        $this->human->write(['comments', 'add', $safari], [], ['Also happens on iOS 18.']);

        // Closed issues.
        $this->human->write(['close', $design], ['reason' => 'Approved in the design review.']);
        $this->agent->write(['close', $flaky], ['reason' => 'Fixed the time-dependent assertion.']);

        return true;
    }

    /**
     * @param  array<string, scalar>  $fields
     */
    private function create(BdRunner $runner, array $fields): string
    {
        $data = $runner->write(['create'], $fields)->json();

        $id = is_array($data) ? ($data['id'] ?? ($data[0]['id'] ?? null)) : null;

        if (! is_string($id)) {
            throw new \RuntimeException('bd create returned no ID for '.($fields['title'] ?? '?'));
        }

        return $id;
    }
}

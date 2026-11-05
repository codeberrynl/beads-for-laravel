<?php

declare(strict_types=1);

namespace Codeberry\Beads\Commands;

use Illuminate\Console\Command;

final class PublishCommand extends Command
{
    protected $signature = 'beads:publish';

    protected $description = 'Publish the Beads UI assets to public/vendor/beads (overwrites)';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'beads-assets', '--force' => true]);

        return self::SUCCESS;
    }
}

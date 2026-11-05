<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('it never starts a shell')
    ->expect('Codeberry\Beads')
    ->not->toUse(['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen']);

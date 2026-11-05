<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The workspace lock could not be acquired in time. The client retries.
 */
final class LockTimeoutException extends RuntimeException
{
    public function __construct(public readonly string $lockFile, public readonly float $timeout)
    {
        parent::__construct(sprintf('The beads workspace is busy (waited %ss for the lock).', rtrim(rtrim(number_format($timeout, 2, '.', ''), '0'), '.')));
    }

    public function render(): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'workspace_busy',
                'message' => $this->getMessage(),
            ],
        ], 503, ['Retry-After' => '1']);
    }
}

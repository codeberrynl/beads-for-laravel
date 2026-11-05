<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotPresenter;
use Illuminate\Http\JsonResponse;

trait RespondsWithIssue
{
    /**
     * The detail payload. It carries the new hash, so after a write the
     * client knows its snapshot is outdated without waiting for a poll.
     */
    protected function issueResponse(Snapshot $snapshot, string $id, int $status = 200): JsonResponse
    {
        $payload = app(SnapshotPresenter::class)->issue($snapshot, $id);

        if ($payload === null) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => "Issue {$id} was not found."]], 404);
        }

        return new JsonResponse($payload, $status);
    }
}

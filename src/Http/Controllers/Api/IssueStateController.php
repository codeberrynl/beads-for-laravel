<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Actions\CloseIssue;
use Codeberry\Beads\Actions\ReopenIssue;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IssueStateController
{
    use RespondsWithIssue;

    public function close(string $id, Request $request, CloseIssue $action, SnapshotRepository $snapshots): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:10000'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $issue = $snapshots->current()->find($id);

        if ($issue === null) {
            return $this->issueResponse($snapshots->current(), $id);
        }

        $snapshot = $action->handle($issue, $data['reason'] ?? null, (bool) ($data['force'] ?? false));

        return $this->issueResponse($snapshot, $id);
    }

    public function reopen(string $id, Request $request, ReopenIssue $action, SnapshotRepository $snapshots): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:10000']]);

        if ($snapshots->current()->find($id) === null) {
            return $this->issueResponse($snapshots->current(), $id);
        }

        return $this->issueResponse($action->handle($id, $data['reason'] ?? null), $id);
    }
}

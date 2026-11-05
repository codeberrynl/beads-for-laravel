<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Snapshot\SnapshotPresenter;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Illuminate\Http\JsonResponse;

final class IssuesController
{
    public function show(string $id, SnapshotRepository $snapshots, SnapshotPresenter $presenter): JsonResponse
    {
        $payload = $presenter->issue($snapshots->current(), $id);

        if ($payload === null) {
            return new JsonResponse(['error' => ['code' => 'not_found', 'message' => "Issue {$id} was not found."]], 404);
        }

        return new JsonResponse($payload);
    }
}

<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Actions\CreateIssue;
use Codeberry\Beads\Actions\UpdateIssue;
use Codeberry\Beads\Http\Requests\CreateIssueRequest;
use Codeberry\Beads\Http\Requests\UpdateIssueRequest;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Illuminate\Http\JsonResponse;

final class IssuesController
{
    use RespondsWithIssue;

    public function show(string $id, SnapshotRepository $snapshots): JsonResponse
    {
        return $this->issueResponse($snapshots->current(), $id);
    }

    public function store(CreateIssueRequest $request, CreateIssue $action): JsonResponse
    {
        [$id, $snapshot] = $action->handle($request->validated());

        return $this->issueResponse($snapshot, $id, 201);
    }

    public function update(string $id, UpdateIssueRequest $request, UpdateIssue $action, SnapshotRepository $snapshots): JsonResponse
    {
        if ($snapshots->current()->find($id) === null) {
            return $this->issueResponse($snapshots->current(), $id);
        }

        return $this->issueResponse($action->handle($id, $request->fields()), $id);
    }
}

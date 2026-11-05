<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Actions\AddComment;
use Codeberry\Beads\Actions\ManageRelation;
use Codeberry\Beads\Http\Requests\RelationRequest;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RelationsController
{
    use RespondsWithIssue;

    public function store(string $id, RelationRequest $request, ManageRelation $action, SnapshotRepository $snapshots): JsonResponse
    {
        if ($snapshots->current()->find($id) === null) {
            return $this->issueResponse($snapshots->current(), $id);
        }

        $data = $request->validated();

        return $this->issueResponse($action->add($id, $data['type'], $data['target'], $request->direction()), $id);
    }

    public function destroy(string $id, RelationRequest $request, ManageRelation $action, SnapshotRepository $snapshots): JsonResponse
    {
        $current = $snapshots->current();

        if ($current->find($id) === null) {
            return $this->issueResponse($current, $id);
        }

        $data = $request->validated();

        return $this->issueResponse($action->remove($current, $id, $data['type'], $data['target'], $request->direction()), $id);
    }

    public function comment(string $id, Request $request, AddComment $action, SnapshotRepository $snapshots): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:100000']]);

        if ($snapshots->current()->find($id) === null) {
            return $this->issueResponse($snapshots->current(), $id);
        }

        return $this->issueResponse($action->handle($id, $data['text']), $id, 201);
    }
}

<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Snapshot\SnapshotPresenter;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SnapshotController
{
    public function __invoke(Request $request, SnapshotRepository $snapshots, SnapshotPresenter $presenter): Response
    {
        $allClosed = $request->query('closed') === 'all';
        $etag = $presenter->etag($snapshots->currentHash(), $allClosed);

        $headers = ['ETag' => $etag, 'Cache-Control' => 'no-cache, private'];

        if (in_array($etag, $request->getETags(), true)) {
            return new Response('', 304, $headers);
        }

        return new JsonResponse($presenter->snapshot($snapshots->current(), $allClosed), 200, $headers);
    }
}

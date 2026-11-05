<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Controllers\Api;

use Codeberry\Beads\Cli\BdVersion;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Data\IssueTypeDefinition;
use Codeberry\Beads\Data\StatusDefinition;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Support\AssetManifest;
use Codeberry\Beads\Workspace\Actor;
use Codeberry\Beads\Workspace\HealthCheck;
use Codeberry\Beads\Workspace\WorkspaceHealth;
use Illuminate\Http\JsonResponse;

final class BootstrapController
{
    public function __invoke(HealthCheck $health, BdVersion $version, Actor $actor, SnapshotRepository $snapshots, AssetManifest $assets): JsonResponse
    {
        $state = $health->state();
        $statuses = [];
        $types = [];
        $hash = null;

        if ($state->isOk()) {
            try {
                $snapshot = $snapshots->current();
                $catalog = $snapshot->catalog();
                $hash = $snapshot->hash;

                $statuses = array_map(fn (StatusDefinition $definition): array => $definition->jsonSerialize() + [
                    'rank' => $catalog->rank($definition->name),
                ], $catalog->ordered());

                $types = array_map(fn (IssueTypeDefinition $definition): array => $definition->jsonSerialize(), $snapshot->typeDefinitions());
            } catch (BdException $exception) {
                if ($exception->errorCode !== BdException::SCHEMA_MIGRATION_REQUIRED) {
                    throw $exception;
                }

                $state = WorkspaceHealth::SchemaMigrationRequired;
            }
        }

        return new JsonResponse([
            'actor' => $actor->name(),
            'humans' => $actor->humans(),
            'statuses' => $statuses,
            'types' => $types,
            'hash' => $hash,
            'bd_version' => $state === WorkspaceHealth::BinaryMissing ? null : $version->current(),
            'health' => $health->details($state),
            'poll_interval' => (int) config('beads.poll_interval', 3),
            'closed_window_days' => (int) config('beads.closed_window_days', 30),
            'assets' => $assets->status(),
        ]);
    }
}

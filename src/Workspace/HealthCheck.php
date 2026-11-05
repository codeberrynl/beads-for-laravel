<?php

declare(strict_types=1);

namespace Codeberry\Beads\Workspace;

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdVersion;
use Illuminate\Http\JsonResponse;

/**
 * Cheap health checks that do not need a bd call into the workspace.
 *
 * "schema_migration_required" can only be detected when bd refuses a call;
 * the BdException for it carries the same health payload.
 */
final class HealthCheck
{
    public function __construct(
        private readonly BdBinary $binary,
        private readonly BdVersion $version,
        private readonly Workspace $workspace,
    ) {}

    public function state(): WorkspaceHealth
    {
        if (! $this->binary->exists()) {
            return WorkspaceHealth::BinaryMissing;
        }

        if (! $this->version->isSupported()) {
            return WorkspaceHealth::VersionTooOld;
        }

        if (! $this->workspace->exists()) {
            return WorkspaceHealth::WorkspaceMissing;
        }

        return WorkspaceHealth::Ok;
    }

    /**
     * @return array{state: string, binary: ?string, searched: list<string>, version: ?string, minimum_version: string, workspace: string}
     */
    public function details(?WorkspaceHealth $state = null): array
    {
        $state ??= $this->state();

        return [
            'state' => $state->value,
            'binary' => $this->binary->path(),
            'searched' => $this->binary->searched(),
            'version' => $this->binary->exists() ? $this->version->current() : null,
            'minimum_version' => BdVersion::MINIMUM,
            'workspace' => $this->workspace->path,
        ];
    }

    /**
     * The response for API calls made while the workspace is not healthy.
     */
    public function response(?WorkspaceHealth $state = null): JsonResponse
    {
        $details = $this->details($state);

        return new JsonResponse([
            'error' => [
                'code' => 'unhealthy',
                'message' => "The beads workspace is not ready ({$details['state']}).",
            ],
            'health' => $details,
        ], 503);
    }
}

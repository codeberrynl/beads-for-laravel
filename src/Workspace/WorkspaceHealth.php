<?php

declare(strict_types=1);

namespace Codeberry\Beads\Workspace;

/**
 * Every state other than Ok is shown by the UI as a full screen.
 */
enum WorkspaceHealth: string
{
    case BinaryMissing = 'binary_missing';
    case VersionTooOld = 'version_too_old';
    case WorkspaceMissing = 'workspace_missing';
    case SchemaMigrationRequired = 'schema_migration_required';
    case Ok = 'ok';

    public function isOk(): bool
    {
        return $this === self::Ok;
    }
}

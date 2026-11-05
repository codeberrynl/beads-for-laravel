<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;

/**
 * bd create with the core fields; the snapshot is refreshed in the lock.
 */
final class CreateIssue
{
    /** API field => bd flag */
    public const FLAGS = [
        'title' => 'title',
        'type' => 'type',
        'priority' => 'priority',
        'status' => 'status',
        'description' => 'description',
        'design' => 'design',
        'acceptance_criteria' => 'acceptance',
        'notes' => 'notes',
        'assignee' => 'assignee',
        'parent' => 'parent',
    ];

    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array{0: string, 1: Snapshot} the new ID and the fresh snapshot
     */
    public function handle(array $data): array
    {
        $flags = [];

        foreach (self::FLAGS as $field => $flag) {
            $value = $data[$field] ?? null;

            if ($value !== null && $value !== '') {
                $flags[$flag] = is_scalar($value) ? (string) $value : null;
            }
        }

        $labels = array_values(array_filter((array) ($data['labels'] ?? []), fn (mixed $label): bool => is_string($label) && trim($label) !== ''));

        if ($labels !== []) {
            $flags['labels'] = implode(',', array_map('trim', $labels));
        }

        [$result, $snapshot] = $this->snapshots->afterWrite(fn () => $this->runner->write(['create'], $flags));

        $payload = $result->json();
        $id = is_array($payload) ? ($payload['id'] ?? ($payload[0]['id'] ?? null)) : null;

        if (! is_string($id)) {
            throw new BdException(BdException::FAILED, 'bd create did not return the new issue ID.');
        }

        return [$id, $snapshot];
    }
}

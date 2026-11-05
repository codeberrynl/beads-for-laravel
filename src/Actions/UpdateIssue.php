<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Actions\Exceptions\ConflictException;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;

/**
 * Writes only the fields that were sent.
 *
 * Pickers (status, priority, type, assignee, parent, labels) are
 * last-write-wins; labels are an add/remove diff. Text fields come with the
 * value the client started from ("base"): inside the lock we first read
 * the issue with bd show, and when the field changed meanwhile we answer
 * with a conflict instead of writing.
 */
final class UpdateIssue
{
    /** API field => bd flag */
    public const TEXT_FIELDS = [
        'title' => 'title',
        'description' => 'description',
        'design' => 'design',
        'acceptance_criteria' => 'acceptance',
        'notes' => 'notes',
    ];

    /** API field => bd flag */
    public const PICKER_FIELDS = [
        'status' => 'status',
        'priority' => 'priority',
        'type' => 'type',
        'assignee' => 'assignee',
        'parent' => 'parent',
    ];

    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function handle(string $id, array $data): Snapshot
    {
        $flags = [];

        foreach ([...self::TEXT_FIELDS, ...self::PICKER_FIELDS] as $field => $flag) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $flags[$flag] = $value === null ? '' : (is_scalar($value) ? (string) $value : '');
            }
        }

        $labels = is_array($data['labels'] ?? null) ? $data['labels'] : [];
        $add = $this->labels($labels['add'] ?? []);
        $remove = array_values(array_diff($this->labels($labels['remove'] ?? []), $add));

        if ($add !== []) {
            $flags['add-label'] = $add;
        }

        if ($remove !== []) {
            $flags['remove-label'] = $remove;
        }

        $textFields = array_values(array_intersect(array_keys(self::TEXT_FIELDS), array_keys($data)));
        $base = is_array($data['base'] ?? null) ? $data['base'] : [];

        return $this->runner->locked(function () use ($id, $flags, $textFields, $base): Snapshot {
            if ($textFields !== []) {
                $this->assertUnchanged($id, $textFields, $base);
            }

            if ($flags === []) {
                return $this->snapshots->current();
            }

            [, $snapshot] = $this->snapshots->afterWrite(fn () => $this->runner->write(['update', $id], $flags));

            return $snapshot;
        });
    }

    /**
     * @param  list<string>  $fields
     * @param  array<mixed>  $base
     */
    private function assertUnchanged(string $id, array $fields, array $base): void
    {
        $current = $this->snapshots->quietly(fn (): ?Issue => Issue::fromShow($this->runner->read(['show', $id])->json(), $id));

        if ($current === null) {
            throw new BdException(BdException::NOT_FOUND, "Issue {$id} was not found.");
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $base)) {
                continue;
            }

            $theirs = $current->text($field);

            if (self::normalise($theirs) !== self::normalise(is_scalar($base[$field]) ? (string) $base[$field] : '')) {
                throw new ConflictException($field, $theirs);
            }
        }
    }

    public static function normalise(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    /**
     * @return list<string>
     */
    private function labels(mixed $labels): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $label): string => is_string($label) ? trim($label) : '', (array) $labels),
            fn (string $label): bool => $label !== '',
        )));
    }
}

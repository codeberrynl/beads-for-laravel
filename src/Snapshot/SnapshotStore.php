<?php

declare(strict_types=1);

namespace Codeberry\Beads\Snapshot;

use Codeberry\Beads\Support\JsonFile;

/**
 * Stores the snapshot in storage/framework/beads/<sha1(workspace)>/:
 * snapshot.json (the data) and meta.json (fingerprint, hash, times), so a
 * 304 only needs the small meta file.
 */
final class SnapshotStore
{
    public function __construct(public readonly string $directory) {}

    /**
     * @return array{fingerprint: string, hash: string, exported_at: string, definitions_at: int}|null
     */
    public function meta(): ?array
    {
        $meta = JsonFile::read($this->directory.'/meta.json');

        if (! is_array($meta) || ! is_string($meta['fingerprint'] ?? null) || ! is_string($meta['hash'] ?? null)) {
            return null;
        }

        return [
            'fingerprint' => $meta['fingerprint'],
            'hash' => $meta['hash'],
            'exported_at' => is_string($meta['exported_at'] ?? null) ? $meta['exported_at'] : '',
            'definitions_at' => is_int($meta['definitions_at'] ?? null) ? $meta['definitions_at'] : 0,
        ];
    }

    public function load(): ?Snapshot
    {
        $data = JsonFile::read($this->directory.'/snapshot.json');

        return $data !== null ? Snapshot::fromArray($data) : null;
    }

    public function save(Snapshot $snapshot, string $fingerprint, int $definitionsAt): void
    {
        JsonFile::write($this->directory.'/snapshot.json', $snapshot->toArray());
        $this->writeMeta($fingerprint, $snapshot->hash, $snapshot->exportedAt, $definitionsAt);
    }

    public function writeMeta(string $fingerprint, string $hash, string $exportedAt, int $definitionsAt): void
    {
        JsonFile::write($this->directory.'/meta.json', [
            'fingerprint' => $fingerprint,
            'hash' => $hash,
            'exported_at' => $exportedAt,
            'definitions_at' => $definitionsAt,
        ]);
    }

    public function updateFingerprint(string $fingerprint): void
    {
        $meta = $this->meta();

        if ($meta !== null) {
            $this->writeMeta($fingerprint, $meta['hash'], $meta['exported_at'], $meta['definitions_at']);
        }
    }
}

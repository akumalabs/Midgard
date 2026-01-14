<?php

namespace App\Data\Server\Proxmox\Snapshot;

use Spatie\LaravelData\Data;

/**
 * SnapshotData - Snapshot DTO following Convoy pattern
 */
class SnapshotData extends Data
{
    public function __construct(
        public string $name,
        public bool $snaptime, // Proxmox returns it as an integer timestamp usually
        public ?string $description,
        public ?string $vmstate,
        public ?bool $parent = false,
    ) {}

    public static function fromProxmox(array $data): self
    {
        return new self(
            name: $data['name'],
            snaptime: isset($data['snaptime']), // We might want formatted date
            description: $data['description'] ?? null,
            vmstate: $data['vmstate'] ?? null,
            parent: isset($data['parent']) // if it lists parent relationship
        );
    }
}

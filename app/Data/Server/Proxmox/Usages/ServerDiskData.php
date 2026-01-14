<?php

namespace App\Data\Server\Proxmox\Usages;

use Spatie\LaravelData\Data;

/**
 * ServerDiskData - Disk usage DTO following Convoy pattern
 */
class ServerDiskData extends Data
{
    public function __construct(
        public int $used,
        public int $total,
        public float $percentage
    ) {}
    
    public static function fromProxmox(array $data): self
    {
        // Example logic
        return new self(
            used: $data['disk'] ?? 0,
            total: $data['maxdisk'] ?? 0,
            percentage: ($data['maxdisk'] ?? 0) > 0 ? (($data['disk'] ?? 0) / $data['maxdisk']) * 100 : 0
        );
    }
}

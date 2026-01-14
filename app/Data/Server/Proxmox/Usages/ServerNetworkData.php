<?php

namespace App\Data\Server\Proxmox\Usages;

use Spatie\LaravelData\Data;

/**
 * ServerNetworkData - Network usage DTO following Convoy pattern
 */
class ServerNetworkData extends Data
{
    public function __construct(
        public int $in,
        public int $out
    ) {}
    
    public static function fromProxmox(array $data): self
    {
        return new self(
            in: $data['netin'] ?? 0,
            out: $data['netout'] ?? 0
        );
    }
}

<?php

namespace App\Services\Servers;

use App\Models\Node;
use App\Models\Server;
use App\Repositories\Proxmox\Node\ProxmoxAllocationRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Exceptions\Service\Server\Allocation\NoUniqueVmidException;
use App\Exceptions\Service\Server\Allocation\InsufficientResourcesException;

/**
 * AllocationService - Smart node selection and resource allocation following Convoy pattern
 */
class AllocationService
{
    /**
     * Find the best node for server placement.
     */
    public function findNode(int $requiredMemoryMb, int $requiredDiskGb, ?array $excludeNodeIds = []): Node
    {
        // Get all active nodes (that are not hidden/disabled)
        $nodes = Node::query()
            ->whereNotIn('id', $excludeNodeIds ?? [])
            ->get();

        $candidates = $nodes->filter(function (Node $node) use ($requiredMemoryMb, $requiredDiskGb) {
            // Check resource availability from DB
            $usedMemory = $node->servers()->sum('memory');
            $usedDisk = $node->servers()->sum('disk');

            $freeMemory = $node->memory - $usedMemory;
            $freeDisk = $node->disk - $usedDisk;

            return $freeMemory >= $requiredMemoryMb && $freeDisk >= $requiredDiskGb;
        });

        if ($candidates->isEmpty()) {
            throw new InsufficientResourcesException("No nodes available with sufficient resources.");
        }

        // Least populated strategy
        return $candidates->sortBy(function (Node $node) {
            return $node->servers()->count();
        })->first();
    }

    /**
     * Get the next available VMID for a node.
     */
    public function getNextVmid(Node $node): int
    {
        $client = new ProxmoxApiClient($node);
        $repository = new ProxmoxAllocationRepository($client);
        $repository->setNode($node);
        
        try {
            return $repository->getNextVmid();
        } catch (\Exception $e) {
            // Fallback: finding max in DB + 1
            $maxDbVmid = Server::max('vmid') ?? 100;
            $nextId = max($maxDbVmid + 1, 100);
            
            // Verify availability loop
            for ($i = 0; $i < 100; $i++) {
                if (!$repository->vmidExists($nextId)) {
                    return $nextId;
                }
                $nextId++;
            }
            
            throw new NoUniqueVmidException("Could not find a unique VMID.");
        }
    }

    /**
     * Generate a unique UUID for the server.
     */
    public function getNextUuid(): string
    {
        return \Illuminate\Support\Str::uuid();
    }
}

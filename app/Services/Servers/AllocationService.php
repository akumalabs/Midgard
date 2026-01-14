<?php

namespace App\Services\Servers;

use App\Models\Node;
use App\Models\Server;
use App\Repositories\Proxmox\Node\ProxmoxAllocationRepository;
use App\Repositories\Proxmox\Node\ProxmoxStatusRepository;
use App\Exceptions\Service\Server\Allocation\NoUniqueVmidException;
use App\Exceptions\Service\Server\Allocation\NoUniqueUuidComboException;
use App\Exceptions\Service\Server\Allocation\InsufficientResourcesException;
use Illuminate\Support\Collection;

/**
 * AllocationService - Smart node selection and resource allocation following Convoy pattern
 */
class AllocationService
{
    public function __construct(
        protected ProxmoxStatusRepository $statusRepository
    ) {}

    /**
     * Find the best node for server placement.
     */
    public function findNode(int $requiredMemoryMb, int $requiredDiskGb, ?array $excludeNodeIds = []): Node
    {
        // Get all active nodes (that are not hidden/disabled)
        $nodes = Node::query()
            ->whereNotIn('id', $excludeNodeIds)
            // ->where('maintenance_mode', false) // assuming we add this later
            ->get();

        $candidates = $nodes->filter(function (Node $node) use ($requiredMemoryMb, $requiredDiskGb) {
            // Check resource availability
            // This assumes we have synced usage data in DB or we fetch live
            // For speed, check DB limits first
            
            $usedMemory = $node->servers()->sum('memory');
            $usedDisk = $node->servers()->sum('disk');

            $freeMemory = $node->memory - $usedMemory;
            $freeDisk = $node->disk - $usedDisk;

            return $freeMemory >= $requiredMemoryMb && $freeDisk >= $requiredDiskGb;
        });

        if ($candidates->isEmpty()) {
            throw new InsufficientResourcesException("No nodes available with sufficient resources.");
        }

        // Simple strategy: Least populated (by count) or Random
        // A better strategy might be least allocated %
        return $candidates->sortBy(function (Node $node) {
            return $node->servers()->count();
        })->first();
    }

    /**
     * Get the next available VMID for a node.
     */
    public function getNextVmid(Node $node): int
    {
        $allocationRepo = new ProxmoxAllocationRepository($node);
        
        try {
            return $allocationRepo->getNextVmid();
        } catch (\Exception $e) {
            // Fallback: finding max in DB + 1, then verifying with Proxmox
            // This is safer if the standard cluster nextid fails
            $maxDbVmid = Server::max('vmid') ?? 100;
            $nextId = max($maxDbVmid + 1, 100);
            
            // Verify availability loop (simple version)
            for ($i = 0; $i < 100; $i++) {
                if (!$allocationRepo->isVmidInUse($nextId)) {
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

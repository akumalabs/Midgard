<?php

namespace App\Services\Isos;

use App\Jobs\Node\MonitorIsoDownloadJob;
use App\Models\Iso;
use App\Models\Node;
use App\Repositories\Proxmox\Node\ProxmoxStorageRepository;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * IsoService - Manages ISO images following Convoy pattern
 */
class IsoService
{
    /**
     * Download an ISO from URL to node storage.
     */
    public function download(Node $node, string $url, string $filename, string $storage = 'local'): Iso
    {
        // Create ISO record
        $iso = Iso::create([
            'node_id' => $node->id,
            'name' => $filename,
            'file_name' => $filename,
            'size' => 0,
            'is_downloading' => true,
        ]);

        // Start download on Proxmox
        $client = new ProxmoxApiClient($node);
        $repository = new ProxmoxStorageRepository($client);
        $repository->setNode($node);
        
        $taskId = $repository->downloadIso($storage, $url, $filename);

        $iso->update(['task_id' => is_array($taskId) ? ($taskId['data'] ?? '') : $taskId]);

        // Dispatch job to monitor download progress
        MonitorIsoDownloadJob::dispatch($iso, $iso->task_id);

        return $iso;
    }

    /**
     * Delete an ISO from node storage.
     */
    public function delete(Iso $iso, string $storage = 'local'): void
    {
        $node = $iso->node;

        // Delete from Proxmox storage
        $client = new ProxmoxApiClient($node);
        $repository = new ProxmoxStorageRepository($client);
        $repository->setNode($node);
        
        $repository->deleteVolume($storage, $iso->file_name);

        // Delete record
        $iso->delete();
    }

    /**
     * List ISOs available on a node.
     */
    public function list(Node $node, string $storage = 'local'): array
    {
        $client = new ProxmoxApiClient($node);
        $repository = new ProxmoxStorageRepository($client);
        $repository->setNode($node);
        
        return $repository->getIsos($storage);
    }

    /**
     * Sync ISOs from node storage to database.
     */
    public function sync(Node $node, string $storage = 'local'): void
    {
        $proxmoxIsos = $this->list($node, $storage);
        $existingIsos = $node->isos()->pluck('file_name')->toArray();

        foreach ($proxmoxIsos as $isoData) {
            $filename = $isoData['volid'] ?? $isoData['name'];
            
            if (!in_array($filename, $existingIsos)) {
                Iso::create([
                    'node_id' => $node->id,
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'file_name' => $filename,
                    'size' => $isoData['size'] ?? 0,
                    'is_downloading' => false,
                ]);
            }
        }
    }
}

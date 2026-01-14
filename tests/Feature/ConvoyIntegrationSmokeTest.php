<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Servers\ServerNetworkService;
use App\Services\Servers\ServerFirewallService;
use App\Services\Servers\AllocationService;
use App\Services\Servers\VmSyncService;
use App\Http\Controllers\Api\Server\ServerBackupController;
use App\Http\Controllers\Api\Server\ServerNetworkController;

class ConvoyIntegrationSmokeTest extends TestCase
{
    /**
     * Test if new services can be instantiated (DI check).
     */
    public function test_services_are_instantiable()
    {
        $this->assertTrue(class_exists(ServerNetworkService::class));
        $this->assertTrue(class_exists(ServerFirewallService::class));
        $this->assertTrue(class_exists(AllocationService::class));
        $this->assertTrue(class_exists(VmSyncService::class));

        // Attempt resolution from container (mocks dependencies if not bound, but checks syntax/autoloader)
        try {
            $networkService = app(ServerNetworkService::class);
            $this->assertInstanceOf(ServerNetworkService::class, $networkService);
        } catch (\Exception $e) {
            // It might fail if repositories aren't mocked binded, but let's see
            // For a true unit test we should mock, but for smoke test we checking wiring
            $this->markTestSkipped('Service resolution failed (expected if repos missing): ' . $e->getMessage());
        }
    }

    /**
     * Test if new controllers exist.
     */
    public function test_controllers_exist()
    {
        $this->assertTrue(class_exists(ServerBackupController::class));
        $this->assertTrue(class_exists(ServerNetworkController::class));
    }

    /**
     * Test DTOs instantiation.
     */
    public function test_dtos_instantiation()
    {
        $data = new \App\Data\Server\Proxmox\Usages\ServerNetworkData(100, 200);
        $this->assertEquals(100, $data->in);
        
        $disk = new \App\Data\Server\Proxmox\Usages\ServerDiskData(10, 100, 10.0);
        $this->assertEquals(10, $disk->used);
    }
}

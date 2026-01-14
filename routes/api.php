<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\NodeController;
use App\Http\Controllers\Api\Admin\ServerController as AdminServerController;
use App\Http\Controllers\Api\Admin\TemplateController;
use App\Http\Controllers\Api\Admin\AddressPoolController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Client\ServerController as ClientServerController;
use App\Http\Controllers\Api\Client\BackupController;
use App\Http\Controllers\Api\Client\SshKeyController;
use App\Http\Controllers\Api\Server\ServerBackupController;
use App\Http\Controllers\Api\Server\ServerNetworkController;
use App\Http\Controllers\Api\Server\ServerFirewallController;
use App\Http\Controllers\Api\Server\ServerConsoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api/v1
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::patch('/user', [AuthController::class, 'update']);
    });

    // Admin routes (requires is_admin)
    Route::prefix('admin')->middleware('admin')->group(function () {
        // Locations
        Route::apiResource('locations', LocationController::class);

        // Nodes
        Route::apiResource('nodes', NodeController::class);
        Route::post('/nodes/{node}/test', [NodeController::class, 'testConnection']);
        Route::post('/nodes/{node}/sync', [NodeController::class, 'sync']);
        Route::get('/nodes/{node}/stats', [NodeController::class, 'stats']);
        Route::get('/nodes/{node}/templates', [TemplateController::class, 'byNode']);
        Route::post('/nodes/{node}/templates/sync', [TemplateController::class, 'sync']);
        Route::get('/nodes/{node}/addresses/available', [AddressPoolController::class, 'available']);

        // Servers
        Route::apiResource('servers', AdminServerController::class);
        Route::post('/servers/{server}/power', [AdminServerController::class, 'power']);
        Route::get('/servers/{server}/status', [AdminServerController::class, 'status']);

        // Extended Server Management (Deep Convoy Integration)
        Route::apiResource('servers.backups', ServerBackupController::class)->only(['index', 'store', 'destroy']);
        Route::post('/servers/{server}/backups/{backup}/restore', [ServerBackupController::class, 'restore']);

        Route::apiResource('servers.network', ServerNetworkController::class)->only(['index', 'store', 'destroy']);

        Route::get('/servers/{server}/firewall', [ServerFirewallController::class, 'index']);
        Route::post('/servers/{server}/firewall', [ServerFirewallController::class, 'store']);
        Route::post('/servers/{server}/firewall/toggle', [ServerFirewallController::class, 'toggle']);
        Route::delete('/servers/{server}/firewall/{pos}', [ServerFirewallController::class, 'destroy']);

        Route::get('/servers/{server}/console/vnc', [ServerConsoleController::class, 'vnc']);

        // Users
        Route::apiResource('users', UserController::class);

        // Templates
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::post('/template-groups', [TemplateController::class, 'storeGroup']);
        Route::put('/template-groups/{group}', [TemplateController::class, 'updateGroup']);
        Route::delete('/template-groups/{group}', [TemplateController::class, 'destroyGroup']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::put('/templates/{template}', [TemplateController::class, 'update']);
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);

        // Address Pools
        Route::apiResource('address-pools', AddressPoolController::class);
        Route::post('/address-pools/{address_pool}/addresses', [AddressPoolController::class, 'addAddresses']);
        Route::post('/address-pools/{address_pool}/range', [AddressPoolController::class, 'addRange']);
        Route::delete('/addresses/{address}', [AddressPoolController::class, 'destroyAddress']);

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/subject', [ActivityLogController::class, 'forSubject']);
    });

    // Client routes (authenticated users)
    Route::prefix('client')->group(function () {
        // Servers
        Route::get('/servers', [ClientServerController::class, 'index']);
        Route::get('/servers/{uuid}', [ClientServerController::class, 'show']);
        Route::get('/servers/{uuid}/status', [ClientServerController::class, 'status']);
        Route::post('/servers/{uuid}/power', [ClientServerController::class, 'power']);
        Route::get('/servers/{uuid}/console', [ClientServerController::class, 'console']);
        
        // Server Settings (Convoy pattern)
        Route::post('/servers/{uuid}/settings/password', [ClientServerController::class, 'updatePassword']);
        Route::post('/servers/{uuid}/settings/iso/mount', [ClientServerController::class, 'mountIso']);
        Route::post('/servers/{uuid}/settings/iso/unmount', [ClientServerController::class, 'unmountIso']);
        
        // Snapshots
        Route::get('/servers/{uuid}/snapshots', [ClientServerController::class, 'listSnapshots']);
        Route::post('/servers/{uuid}/snapshots', [ClientServerController::class, 'createSnapshot']);
        Route::post('/servers/{uuid}/snapshots/{name}/rollback', [ClientServerController::class, 'rollbackSnapshot']);
        Route::delete('/servers/{uuid}/snapshots/{name}', [ClientServerController::class, 'deleteSnapshot']);
        
        // Reinstall
        Route::post('/servers/{uuid}/settings/reinstall', [ClientServerController::class, 'reinstall']);

        // Backups
        Route::get('/servers/{uuid}/backups', [BackupController::class, 'index']);
        Route::post('/servers/{uuid}/backups', [BackupController::class, 'store']);
        Route::delete('/servers/{uuid}/backups/{backup}', [BackupController::class, 'destroy']);
        Route::post('/servers/{uuid}/backups/{backup}/restore', [BackupController::class, 'restore']);
        Route::post('/servers/{uuid}/backups/{backup}/lock', [BackupController::class, 'toggleLock']);

        // SSH Keys
        Route::get('/ssh-keys', [SshKeyController::class, 'index']);
        Route::post('/ssh-keys', [SshKeyController::class, 'store']);
        Route::delete('/ssh-keys/{sshKey}', [SshKeyController::class, 'destroy']);
    });
});

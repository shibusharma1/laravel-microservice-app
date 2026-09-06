<?php

use App\Http\Controllers\Api\V1\IntegrationTokenController;
use App\Http\Controllers\Integration\InboundIntegrationController;
use App\Http\Controllers\Integration\SyncIdController;
use App\Http\Controllers\BusySyncController;

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['internal.key'])
    ->group(function () {
        Route::get('/integrations/token', [IntegrationTokenController::class, 'show']);
    });

Route::post('/integration/inbound', [InboundIntegrationController::class, 'handle']);

Route::post('/integration/sync-ids', [SyncIdController::class, 'handle']);

Route::get('/busy/sync/parties', [BusySyncController::class, 'parties']);
// http://127.0.0.1:8000/api/busy/sync/parties

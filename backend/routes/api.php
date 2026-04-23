<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\DevicePreferenceController;
use App\Http\Controllers\Api\V1\OjsBridgeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::get('/sources', [OjsBridgeController::class, 'sources']);
    Route::get('/journals', [OjsBridgeController::class, 'journals']);
    Route::get('/journals/{journalId}/issues', [OjsBridgeController::class, 'issues']);
    Route::get('/issues/{issueId}/pdf', [OjsBridgeController::class, 'issuePdf']);
    Route::get('/issues/{issueId}/articles', [OjsBridgeController::class, 'articles']);
    Route::get('/articles/{articleId}', [OjsBridgeController::class, 'article']);
    Route::get('/articles/{articleId}/pdf', [OjsBridgeController::class, 'pdf']);
    Route::get('/search', [OjsBridgeController::class, 'search']);
    Route::post('/devices/register', [DevicePreferenceController::class, 'register']);
    Route::put('/devices/preferences', [DevicePreferenceController::class, 'update']);
    Route::get('/devices/preferences/{deviceId}', [DevicePreferenceController::class, 'show']);
});

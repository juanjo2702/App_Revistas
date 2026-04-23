<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogArticle;
use App\Models\CatalogIssue;
use App\Models\CatalogJournal;
use App\Models\DeviceInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
            'catalog' => [
                'journals' => CatalogJournal::query()->count(),
                'issues' => CatalogIssue::query()->count(),
                'articles' => CatalogArticle::query()->count(),
            ],
            'devices' => [
                'registered' => DeviceInstallation::query()->count(),
                'pushEnabled' => DeviceInstallation::query()->where('notifications_enabled', true)->count(),
            ],
        ]);
    }
}

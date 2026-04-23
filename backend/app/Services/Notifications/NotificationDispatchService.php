<?php

namespace App\Services\Notifications;

use App\Models\DeviceInstallation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NotificationDispatchService
{
    public function __construct(private readonly FirebaseMessagingService $messaging)
    {
    }

    /**
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $journals
     * @param  array<int, string|int>  $years
     * @return array{matchedDevices:int,sent:int,failed:int,configured:bool}
     */
    public function dispatch(
        string $title,
        string $body,
        array $sources = [],
        array $journals = [],
        array $years = [],
    ): array {
        $devices = $this->matchingDevices($sources, $journals, $years);

        if (! $this->messaging->isConfigured()) {
            Log::warning('Notification dispatch skipped because FCM is not configured.', [
                'title' => $title,
                'body' => $body,
                'sources' => $sources,
                'journals' => $journals,
                'years' => $years,
                'matched_devices' => $devices->count(),
            ]);

            return [
                'matchedDevices' => $devices->count(),
                'sent' => 0,
                'failed' => 0,
                'configured' => false,
            ];
        }

        $sent = 0;
        $failed = 0;

        /** @var DeviceInstallation $device */
        foreach ($devices as $device) {
            try {
                $this->messaging->sendToToken((string) $device->push_token, [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => [
                        'kind' => 'catalog_update',
                    ],
                ]);

                $sent++;
            } catch (RuntimeException|\Throwable $exception) {
                $failed++;

                Log::warning('Failed to dispatch push notification.', [
                    'device_uuid' => $device->device_uuid,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'matchedDevices' => $devices->count(),
            'sent' => $sent,
            'failed' => $failed,
            'configured' => true,
        ];
    }

    /**
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $journals
     * @param  array<int, string|int>  $years
     * @return Collection<int, DeviceInstallation>
     */
    private function matchingDevices(array $sources, array $journals, array $years): Collection
    {
        /** @var Builder<DeviceInstallation> $query */
        $query = DeviceInstallation::query()
            ->with('segments')
            ->where('notifications_enabled', true)
            ->whereNotNull('push_token');

        if ($sources !== []) {
            $query->whereHas('segments', function (Builder $segment) use ($sources): void {
                $segment
                    ->where('segment_type', 'source')
                    ->whereIn('segment_value', $sources);
            });
        }

        if ($journals !== []) {
            $query->whereHas('segments', function (Builder $segment) use ($journals): void {
                $segment
                    ->where('segment_type', 'journal')
                    ->whereIn('segment_value', $journals);
            });
        }

        if ($years !== []) {
            $query->whereHas('segments', function (Builder $segment) use ($years): void {
                $segment
                    ->where('segment_type', 'year')
                    ->whereIn('segment_value', array_map('strval', $years));
            });
        }

        return $query->get();
    }
}

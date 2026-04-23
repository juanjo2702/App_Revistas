<?php

namespace App\Services\Devices;

use App\Models\DeviceInstallation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DevicePreferenceService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function register(array $payload): DeviceInstallation
    {
        return DB::transaction(function () use ($payload): DeviceInstallation {
            $pushToken = $this->nullableString(Arr::get($payload, 'pushToken'));

            if ($pushToken) {
                DeviceInstallation::query()
                    ->where('push_token', $pushToken)
                    ->where('device_uuid', '!=', $payload['deviceId'])
                    ->update([
                        'push_token' => null,
                        'notifications_enabled' => false,
                    ]);
            }

            $device = DeviceInstallation::query()->updateOrCreate(
                ['device_uuid' => $payload['deviceId']],
                [
                    'platform' => $this->nullableString(Arr::get($payload, 'platform')),
                    'app_version' => $this->nullableString(Arr::get($payload, 'appVersion')),
                    'locale' => $this->nullableString(Arr::get($payload, 'locale')),
                    'push_token' => $pushToken,
                    'push_provider' => $pushToken ? 'fcm' : null,
                    'notifications_enabled' => (bool) Arr::get($payload, 'notificationsEnabled', false),
                    'last_seen_at' => Carbon::now(),
                    'meta' => Arr::get($payload, 'meta'),
                ],
            );

            return $device->load('segments');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updatePreferences(string $deviceId, array $payload): array
    {
        $device = $this->register(array_merge($payload, ['deviceId' => $deviceId]));
        $segments = $this->normalizeSegments($payload);

        DB::transaction(function () use ($device, $segments): void {
            $device->segments()->delete();

            foreach ($segments as $segmentType => $values) {
                foreach ($values as $value) {
                    $device->segments()->create([
                        'segment_type' => $segmentType,
                        'segment_value' => (string) $value,
                    ]);
                }
            }
        });

        return $this->getPreferences($deviceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPreferences(string $deviceId): array
    {
        $device = DeviceInstallation::query()
            ->with('segments')
            ->where('device_uuid', $deviceId)
            ->firstOrFail();

        return [
            'deviceId' => $device->device_uuid,
            'platform' => $device->platform,
            'appVersion' => $device->app_version,
            'locale' => $device->locale,
            'notificationsEnabled' => $device->notifications_enabled,
            'pushConfigured' => filled($device->push_token),
            'followedSources' => $device->segments->where('segment_type', 'source')->pluck('segment_value')->values()->all(),
            'followedJournals' => $device->segments->where('segment_type', 'journal')->pluck('segment_value')->values()->all(),
            'followedYears' => $device->segments->where('segment_type', 'year')->pluck('segment_value')->values()->all(),
            'updatedAt' => $device->updated_at?->toIso8601String(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    private function normalizeSegments(array $payload): array
    {
        return [
            'source' => array_values(array_unique(array_map('strval', array_filter(Arr::wrap($payload['followedSources'] ?? []))))),
            'journal' => array_values(array_unique(array_map('strval', array_filter(Arr::wrap($payload['followedJournals'] ?? []))))),
            'year' => array_values(array_unique(array_map('strval', array_filter(Arr::wrap($payload['followedYears'] ?? []))))),
        ];
    }
}

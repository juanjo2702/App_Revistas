<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Devices\DevicePreferenceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DevicePreferenceController extends Controller
{
    public function __construct(private readonly DevicePreferenceService $preferences)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request, false);
        $device = $this->preferences->register($payload);

        return response()->json([
            'data' => [
                'deviceId' => $device->device_uuid,
                'notificationsEnabled' => $device->notifications_enabled,
                'pushConfigured' => filled($device->push_token),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(string $deviceId): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->preferences->getPreferences($deviceId),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'The requested device preferences do not exist.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request, true);

        try {
            return response()->json([
                'data' => $this->preferences->updatePreferences($payload['deviceId'], $payload),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'The requested device preferences do not exist.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $forPreferences): array
    {
        $validator = Validator::make($request->all(), [
            'deviceId' => ['required', 'uuid'],
            'platform' => ['nullable', 'string', 'max:40'],
            'appVersion' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', 'string', 'max:20'],
            'pushToken' => ['nullable', 'string', 'max:500'],
            'notificationsEnabled' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
            'followedSources' => [$forPreferences ? 'present' : 'nullable', 'array'],
            'followedSources.*' => ['string', 'max:100'],
            'followedJournals' => [$forPreferences ? 'present' : 'nullable', 'array'],
            'followedJournals.*' => ['string', 'max:150'],
            'followedYears' => ['nullable', 'array'],
            'followedYears.*' => ['integer'],
        ]);

        return $validator->validate();
    }
}

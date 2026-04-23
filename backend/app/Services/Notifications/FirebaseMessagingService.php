<?php

namespace App\Services\Notifications;

use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseMessagingService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return filled(config('services.fcm.project_id'))
            && filled(config('services.fcm.credentials'));
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function sendToToken(string $token, array $message): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FCM is not configured for this environment.');
        }

        $response = Http::acceptJson()
            ->withToken($this->accessToken())
            ->post(sprintf(
                'https://fcm.googleapis.com/v1/projects/%s/messages:send',
                config('services.fcm.project_id'),
            ), [
                'message' => array_merge($message, ['token' => $token]),
            ]);

        $response->throw();

        return $response->json();
    }

    private function accessToken(): string
    {
        $cacheKey = 'fcm-access-token';

        return Cache::remember($cacheKey, 3000, function (): string {
            putenv('GOOGLE_APPLICATION_CREDENTIALS='.config('services.fcm.credentials'));

            $credentials = ApplicationDefaultCredentials::getCredentials(self::SCOPE);
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Unable to obtain a Firebase access token.');
            }

            return $accessToken;
        });
    }
}

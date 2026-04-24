<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_empty_followed_segments_when_updating_preferences(): void
    {
        $response = $this->putJson('/api/v1/devices/preferences', [
            'deviceId' => '7c61e9b0-42d8-4f17-bc52-6f7d460bef62',
            'platform' => 'android',
            'appVersion' => '1.0',
            'locale' => 'es-BO',
            'notificationsEnabled' => true,
            'followedSources' => [],
            'followedJournals' => [],
            'followedYears' => [],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.deviceId', '7c61e9b0-42d8-4f17-bc52-6f7d460bef62')
            ->assertJsonPath('data.notificationsEnabled', true)
            ->assertJsonPath('data.followedSources', [])
            ->assertJsonPath('data.followedJournals', [])
            ->assertJsonPath('data.followedYears', []);
    }
}

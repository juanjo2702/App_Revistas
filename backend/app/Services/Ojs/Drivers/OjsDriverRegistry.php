<?php

namespace App\Services\Ojs\Drivers;

use App\Exceptions\BridgeException;

class OjsDriverRegistry
{
    public function __construct(
        private readonly RestV1OjsDriver $restV1,
        private readonly PublicOjs34Driver $publicOjs34,
    ) {
    }

    public function forSource(array $source): OjsDriver
    {
        return match ($source['driver'] ?? 'rest_v1') {
            'rest_v1' => $this->restV1,
            'public_ojs_34' => $this->publicOjs34,
            default => throw new BridgeException('The configured OJS driver is not supported.', 500),
        };
    }
}

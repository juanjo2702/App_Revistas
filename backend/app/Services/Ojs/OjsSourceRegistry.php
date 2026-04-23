<?php

namespace App\Services\Ojs;

use App\Exceptions\BridgeException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class OjsSourceRegistry
{
    public function all(): Collection
    {
        return collect(config('ojs.sources', []))
            ->filter(static fn (array $source): bool => (bool) Arr::get($source, 'enabled', false) && filled(Arr::get($source, 'slug')))
            ->values();
    }

    public function find(string $slug): array
    {
        $source = $this->all()->firstWhere('slug', $slug);

        if (! is_array($source)) {
            throw new BridgeException("Unknown OJS source [{$slug}].", 404);
        }

        return $source;
    }
}

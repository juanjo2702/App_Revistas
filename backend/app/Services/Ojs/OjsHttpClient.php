<?php

namespace App\Services\Ojs;

use App\Exceptions\BridgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OjsHttpClient
{
    /**
     * @return array<string, mixed>
     */
    public function getJson(array $source, string $pathOrUrl, array $query = []): array
    {
        $url = $this->resolveUrl($source, $pathOrUrl, 'api_base_url');
        $cacheKey = sprintf('ojs-json:%s', sha1(($source['slug'] ?? 'source').$url.serialize($query)));

        return Cache::remember($cacheKey, config('ojs.http.cache_ttl_seconds'), function () use ($source, $url, $query): array {
            try {
                $response = $this->requestFor($source)->acceptJson()->get($url, $query);
                $response->throw();
            } catch (ConnectionException $exception) {
                throw new BridgeException('The upstream OJS source is unavailable right now.', 502, $exception);
            } catch (RequestException $exception) {
                $status = $exception->response?->status() ?? 502;
                throw new BridgeException('The upstream OJS source rejected the request.', $status >= 400 && $status < 500 ? 404 : 502, $exception);
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new BridgeException('The upstream OJS source returned an unexpected payload.', 502);
            }

            return $payload;
        });
    }

    public function getHtml(array $source, string $url, array $query = []): string
    {
        return $this->getText($source, $url, $query, 'text/html,application/xhtml+xml');
    }

    public function getXml(array $source, string $url, array $query = []): string
    {
        return $this->getText($source, $url, $query, 'application/xml,text/xml');
    }

    public function stream(array $source, string $url, ?string $range = null): Response
    {
        try {
            $response = $this->requestFor($source)
                ->withHeaders(array_filter([
                    'Range' => $range,
                ]))
                ->withOptions(['stream' => true])
                ->send('GET', $url);

            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new BridgeException('Unable to stream the requested PDF from the upstream OJS source.', 502, $exception);
        }

        return $response;
    }

    public function absoluteUrl(array $source, string $url, string $baseKey = 'base_url'): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $baseUrl = data_get($source, $baseKey) ?? data_get($source, 'base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new BridgeException('The OJS source does not expose a usable base URL.', 500);
        }

        return rtrim($baseUrl, '/').'/'.ltrim($url, '/');
    }

    private function getText(array $source, string $url, array $query, string $accept): string
    {
        $resolvedUrl = $this->resolveUrl($source, $url, 'base_url');
        $cacheKey = sprintf('ojs-text:%s', sha1(($source['slug'] ?? 'source').$accept.$resolvedUrl.serialize($query)));

        return Cache::remember($cacheKey, config('ojs.http.cache_ttl_seconds'), function () use ($source, $resolvedUrl, $query, $accept): string {
            try {
                $response = $this->requestFor($source)
                    ->accept($accept)
                    ->get($resolvedUrl, $query);

                $response->throw();
            } catch (ConnectionException $exception) {
                throw new BridgeException('The upstream OJS source is unavailable right now.', 502, $exception);
            } catch (RequestException $exception) {
                $status = $exception->response?->status() ?? 502;
                throw new BridgeException('The upstream OJS source rejected the request.', $status >= 400 && $status < 500 ? 404 : 502, $exception);
            }

            return $response->body();
        });
    }

    private function requestFor(array $source): PendingRequest
    {
        $request = Http::timeout((int) config('ojs.http.timeout'))
            ->connectTimeout((int) config('ojs.http.connect_timeout'))
            ->retry(
                times: (int) config('ojs.http.retry_times'),
                sleepMilliseconds: (int) config('ojs.http.retry_sleep_ms'),
                throw: false,
            );

        if ($token = Arr::get($source, 'token')) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    private function resolveUrl(array $source, string $pathOrUrl, string $baseKey): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            return $pathOrUrl;
        }

        $baseUrl = data_get($source, $baseKey);

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new BridgeException('The OJS source is missing a required base URL.', 500);
        }

        return rtrim($baseUrl, '/').'/'.ltrim($pathOrUrl, '/');
    }
}

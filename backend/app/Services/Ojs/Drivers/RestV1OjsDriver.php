<?php

namespace App\Services\Ojs\Drivers;

use App\Exceptions\BridgeException;
use App\Services\Ojs\OjsHttpClient;
use App\Services\Ojs\OjsId;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RestV1OjsDriver implements OjsDriver
{
    public function __construct(private readonly OjsHttpClient $http)
    {
    }

    public function describeSource(array $source): array
    {
        return [
            'slug' => $source['slug'],
            'name' => $source['name'],
            'driver' => $source['driver'] ?? 'rest_v1',
            'baseUrl' => rtrim((string) ($source['base_url'] ?? ''), '/'),
            'apiBaseUrl' => rtrim((string) ($source['api_base_url'] ?? ''), '/') ?: null,
        ];
    }

    public function listJournals(array $source): array
    {
        return $this->fetchContexts($source)
            ->map(fn (array $context): array => $this->normalizeJournal($source, $context))
            ->values()
            ->all();
    }

    public function listIssues(array $source, string $journalId): array
    {
        $context = $this->resolveContext($source, $journalId);
        $issues = $this->fetchIssuesPayload($source, $journalId, $context);

        return collect($issues)
            ->map(fn (array $issue): array => $this->normalizeIssue($source, $journalId, $issue))
            ->values()
            ->all();
    }

    public function getIssue(array $source, string $journalId, string $issueId): array
    {
        $issue = collect($this->listIssues($source, $journalId))
            ->firstWhere('remoteId', $issueId);

        if (! is_array($issue)) {
            throw new BridgeException('The requested issue was not found in the upstream OJS source.', 404);
        }

        return $issue;
    }

    public function listArticles(array $source, string $journalId, string $issueId): array
    {
        $context = $this->resolveContext($source, $journalId);
        $submissions = $this->fetchSubmissionsPayload(
            source: $source,
            journalId: $journalId,
            context: $context,
            query: [
                'issueIds[]' => $issueId,
                'count' => 100,
                'status' => 3,
            ],
        )->filter(fn (array $submission): bool => (string) data_get($submission, 'issueId') === $issueId || (string) data_get($submission, 'currentPublication.issueId') === $issueId);

        return $submissions
            ->map(fn (array $submission): array => $this->normalizeArticleSummary($source, $journalId, $submission))
            ->values()
            ->all();
    }

    public function getArticle(array $source, string $journalId, string $articleId): array
    {
        $context = $this->resolveContext($source, $journalId);
        $submission = $this->fetchSubmissionDetail($source, $journalId, $articleId, $context);

        return $this->normalizeArticleDetail($source, $journalId, $submission);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchContexts(array $source): Collection
    {
        $cacheKey = sprintf('ojs-contexts:%s', $source['slug']);

        return Cache::remember($cacheKey, config('ojs.http.cache_ttl_seconds'), function () use ($source): Collection {
            $payload = $this->fetchPayload($source, [
                ['path' => '/contexts'],
                ['path' => '/journals'],
            ]);

            return collect($this->extractItems($payload));
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchIssuesPayload(array $source, string $journalId, ?array $context = null): array
    {
        $payload = $this->fetchPayload($source, [
            [
                'url' => $this->appendPath(data_get($context, '_href'), '/issues'),
            ],
            [
                'path' => sprintf('/contexts/%s/issues', $journalId),
            ],
            [
                'path' => '/issues',
                'query' => ['contextId' => $journalId],
            ],
        ]);

        return $this->extractItems($payload);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchSubmissionsPayload(array $source, string $journalId, ?array $context, array $query): Collection
    {
        $payload = $this->fetchPayload($source, [
            [
                'url' => $this->appendPath(data_get($context, '_href'), '/submissions'),
                'query' => $query,
            ],
            [
                'path' => '/submissions',
                'query' => array_merge(['contextId' => $journalId], $query),
            ],
            [
                'path' => sprintf('/issues/%s/submissions', $query['issueIds[]'] ?? ''),
            ],
        ]);

        return collect($this->extractItems($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSubmissionDetail(array $source, string $journalId, string $submissionId, ?array $context = null): array
    {
        $submission = $this->fetchPayload($source, [
            [
                'url' => $this->appendPath(data_get($context, '_href'), sprintf('/submissions/%s', $submissionId)),
            ],
            [
                'path' => sprintf('/submissions/%s', $submissionId),
                'query' => ['contextId' => $journalId],
            ],
        ]);

        $currentPublication = $this->resolveCurrentPublication($submission);
        $publicationHref = data_get($currentPublication, '_href');

        if (is_string($publicationHref) && $publicationHref !== '') {
            try {
                $submission['publicationDetail'] = $this->http->getJson($source, $publicationHref);
            } catch (BridgeException) {
                // Keep the submission payload even if a secondary publication request fails.
            }
        }

        return $submission;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContext(array $source, string $journalId): ?array
    {
        return $this->fetchContexts($source)
            ->first(fn (array $context): bool => (string) data_get($context, 'id') === $journalId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function fetchPayload(array $source, array $candidates): array
    {
        $errors = [];

        foreach ($candidates as $candidate) {
            $url = $candidate['url'] ?? null;
            $path = $candidate['path'] ?? null;

            if (! is_string($url) && ! is_string($path)) {
                continue;
            }

            try {
                return $this->http->getJson($source, $url ?? $path, $candidate['query'] ?? []);
            } catch (BridgeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        throw new BridgeException($errors !== [] ? end($errors) : 'No compatible OJS endpoint was available for this resource.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $payload): array
    {
        $items = Arr::get($payload, 'items', $payload);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeJournal(array $source, array $context): array
    {
        $remoteId = (string) data_get($context, 'id');

        return [
            'id' => OjsId::journal($source['slug'], $remoteId),
            'source' => $source['slug'],
            'remoteId' => $remoteId,
            'name' => $this->pickLocalized(data_get($context, 'name')) ?? data_get($context, 'abbreviation') ?? $source['name'],
            'description' => $this->pickLocalized(data_get($context, 'description')),
            'issn' => data_get($context, 'onlineIssn') ?? data_get($context, 'printIssn'),
            'url' => data_get($context, 'url') ?? $this->toPublicUrl($source, data_get($context, 'path', '')),
            'thumbnailUrl' => data_get($context, 'journalThumbnail') ?? data_get($context, 'thumbnailUrl'),
            'apiHref' => data_get($context, '_href'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeIssue(array $source, string $journalId, array $issue): array
    {
        $remoteId = (string) data_get($issue, 'id');
        $title = $this->pickLocalized(data_get($issue, 'title'))
            ?? data_get($issue, 'issueIdentification')
            ?? trim(sprintf('Vol. %s No. %s', data_get($issue, 'volume', '-'), data_get($issue, 'number', '-')));

        return [
            'id' => OjsId::issue($source['slug'], $journalId, $remoteId),
            'journalId' => OjsId::journal($source['slug'], $journalId),
            'remoteId' => $remoteId,
            'title' => trim($title),
            'volume' => data_get($issue, 'volume'),
            'number' => data_get($issue, 'number'),
            'year' => data_get($issue, 'year'),
            'publishedAt' => data_get($issue, 'datePublished'),
            'description' => $this->pickLocalized(data_get($issue, 'description')),
            'coverUrl' => data_get($issue, 'coverImage.url') ?? data_get($issue, 'coverImageUrl'),
            'url' => data_get($issue, 'urlPublished') ?? data_get($issue, 'url'),
            'pdf' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArticleSummary(array $source, string $journalId, array $submission): array
    {
        $remoteId = (string) data_get($submission, 'id');
        $publication = $this->resolvePublicationPayload($submission);
        $pdf = $this->extractPdfAsset($source, $submission, $publication, $remoteId);

        return [
            'id' => OjsId::article($source['slug'], $journalId, $remoteId),
            'journalId' => OjsId::journal($source['slug'], $journalId),
            'remoteId' => $remoteId,
            'issueId' => data_get($publication, 'issueId')
                ? OjsId::issue($source['slug'], $journalId, (string) data_get($publication, 'issueId'))
                : null,
            'title' => $this->pickLocalized(data_get($publication, 'title')) ?? data_get($submission, 'title') ?? 'Untitled article',
            'subtitle' => $this->pickLocalized(data_get($publication, 'subtitle')),
            'authors' => $this->normalizeAuthors($publication),
            'authorsString' => data_get($publication, 'authorsString') ?? data_get($submission, 'authorsString'),
            'abstract' => $this->pickLocalized(data_get($publication, 'abstract')),
            'keywords' => array_values(array_filter(Arr::wrap(data_get($publication, 'keywords')))),
            'doi' => data_get($publication, 'doi') ?? data_get($submission, 'doi'),
            'pages' => data_get($publication, 'pages'),
            'publishedAt' => data_get($submission, 'datePublished') ?? data_get($publication, 'datePublished'),
            'url' => data_get($publication, 'urlPublished') ?? data_get($submission, 'urlPublished'),
            'pdf' => $pdf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArticleDetail(array $source, string $journalId, array $submission): array
    {
        $summary = $this->normalizeArticleSummary($source, $journalId, $submission);
        $publication = $this->resolvePublicationPayload($submission);

        return array_merge($summary, [
            'citations' => Arr::wrap(data_get($publication, 'citations')),
            'licenseUrl' => data_get($publication, 'licenseUrl'),
            'references' => Arr::wrap(data_get($publication, 'references')),
            'section' => $this->pickLocalized(data_get($publication, 'sectionTitle')) ?? data_get($publication, 'section'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAuthors(array $publication): array
    {
        return collect(Arr::wrap(data_get($publication, 'authors')))
            ->filter(static fn (mixed $author): bool => is_array($author))
            ->map(fn (array $author): string => trim(
                implode(' ', array_filter([
                    data_get($author, 'givenName'),
                    data_get($author, 'familyName'),
                ]))
            ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePublicationPayload(array $submission): array
    {
        $publicationDetail = data_get($submission, 'publicationDetail');

        if (is_array($publicationDetail) && $publicationDetail !== []) {
            return $publicationDetail;
        }

        return $this->resolveCurrentPublication($submission);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCurrentPublication(array $submission): array
    {
        $publications = collect(Arr::wrap(data_get($submission, 'publications')))
            ->filter(static fn (mixed $publication): bool => is_array($publication))
            ->values();

        if ($publications->isEmpty()) {
            return Arr::wrap(data_get($submission, 'currentPublication'));
        }

        $currentPublicationId = data_get($submission, 'currentPublicationId');

        if ($currentPublicationId !== null) {
            $matched = $publications->first(fn (array $publication): bool => (string) data_get($publication, 'id') === (string) $currentPublicationId);

            if (is_array($matched)) {
                return $matched;
            }
        }

        return $publications->last() ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPdfAsset(array $source, array $submission, array $publication, string $fallbackId): ?array
    {
        $galleyCandidates = collect()
            ->merge(Arr::wrap(data_get($publication, 'galleys')))
            ->merge(Arr::wrap(data_get($publication, 'publicationGalleys')))
            ->merge(Arr::wrap(data_get($submission, 'galleys')))
            ->merge(Arr::wrap(data_get($submission, 'submissionGalleys')))
            ->filter(static fn (mixed $candidate): bool => is_array($candidate))
            ->values();

        $galley = $galleyCandidates->first(function (array $candidate): bool {
            $mime = strtolower((string) (data_get($candidate, 'mimeType')
                ?? data_get($candidate, 'mimetype')
                ?? data_get($candidate, 'submissionFile.mimetype')));

            $label = strtolower(implode(' ', array_filter([
                data_get($candidate, 'label'),
                data_get($candidate, 'name'),
                data_get($candidate, 'submissionFile.name'),
            ])));

            $url = strtolower((string) ($this->resolvePdfUrl($candidate) ?? ''));

            return str_contains($mime, 'pdf')
                || str_contains($label, 'pdf')
                || Str::endsWith($url, '.pdf');
        });

        if (! is_array($galley)) {
            return null;
        }

        $pdfUrl = $this->resolvePdfUrl($galley);

        if (! is_string($pdfUrl) || $pdfUrl === '') {
            return null;
        }

        $title = $this->pickLocalized(data_get($publication, 'title')) ?? data_get($submission, 'title') ?? $fallbackId;
        $filename = data_get($galley, 'submissionFile.name')
            ?? data_get($galley, 'name')
            ?? Str::slug(Str::limit($title, 80, ''), '-').'.pdf';

        if (! Str::endsWith(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return [
            'url' => $this->http->absoluteUrl($source, $pdfUrl),
            'mimeType' => data_get($galley, 'submissionFile.mimetype') ?? data_get($galley, 'mimeType') ?? 'application/pdf',
            'filename' => $filename,
            'downloadable' => true,
        ];
    }

    private function resolvePdfUrl(array $galley): ?string
    {
        return data_get($galley, 'urlPublished')
            ?? data_get($galley, 'urlRemote')
            ?? data_get($galley, 'submissionFile.url')
            ?? data_get($galley, 'file.url');
    }

    private function pickLocalized(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim(strip_tags($value)) ?: null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (['es_ES', 'es_BO', 'es', 'en_US', 'en'] as $locale) {
            if (is_string($value[$locale] ?? null) && trim($value[$locale]) !== '') {
                return trim(strip_tags($value[$locale]));
            }
        }

        foreach ($value as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                return trim(strip_tags($entry));
            }
        }

        return null;
    }

    private function toPublicUrl(array $source, string $relativePath): string
    {
        return $this->http->absoluteUrl($source, $relativePath);
    }

    private function appendPath(?string $baseUrl, string $suffix): ?string
    {
        if (! is_string($baseUrl) || $baseUrl === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($suffix, '/');
    }
}

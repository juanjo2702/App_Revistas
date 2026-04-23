<?php

namespace App\Services\Catalog;

use App\Models\CatalogArticle;
use App\Models\CatalogIssue;
use App\Models\CatalogJournal;
use App\Services\Ojs\OjsBridgeService;
use App\Services\Ojs\OjsId;
use App\Services\Ojs\OjsSourceRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CatalogSyncService
{
    public function __construct(
        private readonly OjsBridgeService $remoteCatalog,
        private readonly OjsSourceRegistry $sources,
    ) {
    }

    /**
     * @return array{sources:int,journals:int,issues:int,articles:int}
     */
    public function syncAllSources(): array
    {
        $summary = [
            'sources' => 0,
            'journals' => 0,
            'issues' => 0,
            'articles' => 0,
        ];

        foreach ($this->sources->all() as $source) {
            $result = $this->syncSource($source['slug']);
            $summary['sources']++;
            $summary['journals'] += $result['journals'];
            $summary['issues'] += $result['issues'];
            $summary['articles'] += $result['articles'];
        }

        return $summary;
    }

    /**
     * @return array{journals:int,issues:int,articles:int}
     */
    public function syncSource(string $sourceSlug): array
    {
        $journals = $this->remoteCatalog->listJournals($sourceSlug);
        $summary = [
            'journals' => 0,
            'issues' => 0,
            'articles' => 0,
        ];

        foreach ($journals as $journalPayload) {
            $journal = $this->upsertJournal($journalPayload);
            $summary['journals']++;

            $nested = $this->syncJournal($journal->compound_id);
            $summary['issues'] += $nested['issues'];
            $summary['articles'] += $nested['articles'];
        }

        return $summary;
    }

    /**
     * @return array{issues:int,articles:int}
     */
    public function syncJournal(string $journalCompoundId): array
    {
        $journalPayload = $this->ensureJournalPayload($journalCompoundId);
        $journal = $this->upsertJournal($journalPayload);
        $issues = $this->remoteCatalog->listIssues($journalCompoundId);

        $summary = [
            'issues' => 0,
            'articles' => 0,
        ];

        foreach ($issues as $issuePayload) {
            $issue = $this->upsertIssue($journal, $issuePayload);
            $summary['issues']++;

            $articles = $this->remoteCatalog->listArticles($issue->compound_id);

            foreach ($articles as $articleSummary) {
                $detail = $this->remoteCatalog->getArticle($articleSummary['id']);
                $this->upsertArticle($journal, $issue, $detail);
                $summary['articles']++;
            }
        }

        return $summary;
    }

    public function syncArticle(string $articleCompoundId): CatalogArticle
    {
        $articleRef = OjsId::parseArticle($articleCompoundId);
        $journalCompoundId = OjsId::journal($articleRef['source'], $articleRef['journalId']);
        $journalPayload = $this->ensureJournalPayload($journalCompoundId);
        $journal = $this->upsertJournal($journalPayload);
        $detail = $this->remoteCatalog->getArticle($articleCompoundId);

        $issue = null;
        $issueCompoundId = Arr::get($detail, 'issueId');

        if (is_string($issueCompoundId) && $issueCompoundId !== '') {
            $issuePayload = collect($this->remoteCatalog->listIssues($journalCompoundId))
                ->firstWhere('id', $issueCompoundId);

            if (is_array($issuePayload)) {
                $issue = $this->upsertIssue($journal, $issuePayload);
            } else {
                $issue = CatalogIssue::query()->where('compound_id', $issueCompoundId)->first();
            }
        }

        return $this->upsertArticle($journal, $issue, $detail);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertJournal(array $payload): CatalogJournal
    {
        return DB::transaction(function () use ($payload): CatalogJournal {
            $journal = CatalogJournal::query()->updateOrCreate(
                ['compound_id' => $payload['id']],
                [
                    'source_slug' => $payload['source'],
                    'remote_id' => (string) $payload['remoteId'],
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                    'issn' => $payload['issn'] ?? null,
                    'url' => $payload['url'] ?? null,
                    'thumbnail_url' => $payload['thumbnailUrl'] ?? null,
                    'api_href' => $payload['apiHref'] ?? null,
                    'synced_at' => now(),
                ],
            );

            return $journal->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertIssue(CatalogJournal $journal, array $payload): CatalogIssue
    {
        return DB::transaction(function () use ($journal, $payload): CatalogIssue {
            $issue = CatalogIssue::query()->updateOrCreate(
                ['compound_id' => $payload['id']],
                [
                    'journal_id' => $journal->id,
                    'journal_compound_id' => $journal->compound_id,
                    'source_slug' => $journal->source_slug,
                    'remote_id' => (string) $payload['remoteId'],
                    'title' => $payload['title'],
                    'volume' => Arr::get($payload, 'volume'),
                    'number' => Arr::get($payload, 'number'),
                    'year' => $this->normalizeYear(Arr::get($payload, 'year')),
                    'published_at' => $this->normalizeDate(Arr::get($payload, 'publishedAt')),
                    'description' => Arr::get($payload, 'description'),
                    'cover_url' => Arr::get($payload, 'coverUrl'),
                    'url' => Arr::get($payload, 'url'),
                    'pdf' => Arr::get($payload, 'pdf'),
                    'synced_at' => now(),
                ],
            );

            return $issue->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertArticle(CatalogJournal $journal, ?CatalogIssue $issue, array $payload): CatalogArticle
    {
        $authors = array_values(array_filter(Arr::wrap($payload['authors'] ?? []), 'is_string'));
        $keywords = array_values(array_filter(Arr::wrap($payload['keywords'] ?? []), 'is_string'));
        $citations = array_values(array_filter(Arr::wrap($payload['citations'] ?? []), 'is_string'));
        $references = array_values(array_filter(Arr::wrap($payload['references'] ?? []), 'is_string'));

        return DB::transaction(function () use ($journal, $issue, $payload, $authors, $keywords, $citations, $references): CatalogArticle {
            $article = CatalogArticle::query()->updateOrCreate(
                ['compound_id' => $payload['id']],
                [
                    'journal_id' => $journal->id,
                    'issue_id' => $issue?->id,
                    'journal_compound_id' => $journal->compound_id,
                    'issue_compound_id' => $issue?->compound_id ?? Arr::get($payload, 'issueId'),
                    'source_slug' => $journal->source_slug,
                    'remote_id' => (string) $payload['remoteId'],
                    'title' => $payload['title'],
                    'subtitle' => Arr::get($payload, 'subtitle'),
                    'authors' => $authors,
                    'authors_string' => Arr::get($payload, 'authorsString'),
                    'abstract' => Arr::get($payload, 'abstract'),
                    'keywords' => $keywords,
                    'doi' => Arr::get($payload, 'doi'),
                    'pages' => Arr::get($payload, 'pages'),
                    'published_at' => $this->normalizeDate(Arr::get($payload, 'publishedAt')),
                    'url' => Arr::get($payload, 'url'),
                    'pdf' => Arr::get($payload, 'pdf'),
                    'citations' => $citations,
                    'license_url' => Arr::get($payload, 'licenseUrl'),
                    'references' => $references,
                    'section' => Arr::get($payload, 'section'),
                    'search_blob' => $this->buildSearchBlob($journal, $issue, $payload, $authors, $keywords),
                    'synced_at' => now(),
                ],
            );

            return $article->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureJournalPayload(string $journalCompoundId): array
    {
        $journalRef = OjsId::parseJournal($journalCompoundId);
        $payload = collect($this->remoteCatalog->listJournals($journalRef['source']))
            ->firstWhere('id', $journalCompoundId);

        if (is_array($payload)) {
            return $payload;
        }

        $journal = CatalogJournal::query()
            ->where('compound_id', $journalCompoundId)
            ->first();

        return [
            'id' => $journalCompoundId,
            'source' => $journalRef['source'],
            'remoteId' => $journalRef['journalId'],
            'name' => $journal?->name ?? $journalCompoundId,
            'description' => $journal?->description,
            'issn' => $journal?->issn,
            'url' => $journal?->url,
            'thumbnailUrl' => $journal?->thumbnail_url,
            'apiHref' => $journal?->api_href,
        ];
    }

    private function buildSearchBlob(
        CatalogJournal $journal,
        ?CatalogIssue $issue,
        array $payload,
        array $authors,
        array $keywords,
    ): string {
        return implode(' ', array_filter([
            $payload['title'] ?? null,
            Arr::get($payload, 'subtitle'),
            Arr::get($payload, 'authorsString'),
            implode(' ', $authors),
            Arr::get($payload, 'abstract'),
            implode(' ', $keywords),
            Arr::get($payload, 'doi'),
            Arr::get($payload, 'section'),
            $journal->name,
            $issue?->title,
            (string) ($issue?->year ?? ''),
        ]));
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}

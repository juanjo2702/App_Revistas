<?php

namespace App\Services\Catalog;

use App\Models\CatalogArticle;
use App\Models\CatalogIssue;
use App\Models\CatalogJournal;
use App\Services\Ojs\OjsBridgeService;
use App\Services\Ojs\OjsSourceRegistry;
use Illuminate\Database\Eloquent\Builder;

class CatalogQueryService
{
    public function __construct(
        private readonly OjsSourceRegistry $sources,
        private readonly OjsBridgeService $remoteCatalog,
        private readonly CatalogSyncService $sync,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSources(): array
    {
        return $this->remoteCatalog->listSources();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJournals(string $sourceSlug): array
    {
        $this->sources->find($sourceSlug);

        if (! CatalogJournal::query()->where('source_slug', $sourceSlug)->exists()) {
            $this->sync->syncSource($sourceSlug);
        }

        return CatalogJournal::query()
            ->where('source_slug', $sourceSlug)
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogJournal $journal): array => $this->serializeJournal($journal))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(string $journalCompoundId): array
    {
        $journal = CatalogJournal::query()->where('compound_id', $journalCompoundId)->first();

        if (! $journal) {
            $this->sync->syncJournal($journalCompoundId);
            $journal = CatalogJournal::query()->where('compound_id', $journalCompoundId)->firstOrFail();
        }

        if (! $journal->issues()->exists()) {
            $this->sync->syncJournal($journalCompoundId);
            $journal->refresh();
        }

        return $journal->issues()
            ->orderByDesc('published_at')
            ->orderByDesc('year')
            ->get()
            ->map(fn (CatalogIssue $issue): array => $this->serializeIssue($issue))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listArticles(string $issueCompoundId): array
    {
        $issue = CatalogIssue::query()->where('compound_id', $issueCompoundId)->first();

        if (! $issue) {
            $journalCompoundId = $this->extractJournalCompoundIdFromIssue($issueCompoundId);
            $this->sync->syncJournal($journalCompoundId);
            $issue = CatalogIssue::query()->where('compound_id', $issueCompoundId)->firstOrFail();
        }

        if (! $issue->articles()->exists()) {
            $this->sync->syncJournal($issue->journal_compound_id);
            $issue->refresh();
        }

        return $issue->articles()
            ->orderBy('title')
            ->get()
            ->map(fn (CatalogArticle $article): array => $this->serializeArticle($article))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getArticle(string $articleCompoundId): array
    {
        $article = CatalogArticle::query()
            ->with(['journal', 'issue'])
            ->where('compound_id', $articleCompoundId)
            ->first();

        if (! $article) {
            $this->sync->syncArticle($articleCompoundId);
            $article = CatalogArticle::query()
                ->with(['journal', 'issue'])
                ->where('compound_id', $articleCompoundId)
                ->firstOrFail();
        }

        return $this->serializeArticle($article, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchArticles(string $query, ?string $sourceSlug = null, ?int $year = null): array
    {
        if (! CatalogArticle::query()->exists()) {
            $this->sync->syncAllSources();
        }

        $normalizedQuery = trim($query);

        /** @var Builder<CatalogArticle> $builder */
        $builder = CatalogArticle::query()
            ->with(['journal', 'issue'])
            ->when($sourceSlug, fn (Builder $search): Builder => $search->where('source_slug', $sourceSlug))
            ->when($year, fn (Builder $search): Builder => $search->whereHas('issue', fn (Builder $issue): Builder => $issue->where('year', $year)));

        if ($normalizedQuery !== '') {
            $builder->where(function (Builder $search) use ($normalizedQuery): void {
                $like = '%'.$normalizedQuery.'%';
                $search
                    ->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('authors_string', 'like', $like)
                    ->orWhere('doi', 'like', $like)
                    ->orWhere('search_blob', 'like', $like);
            });
        }

        return $builder
            ->orderByDesc('published_at')
            ->orderBy('title')
            ->limit(50)
            ->get()
            ->map(fn (CatalogArticle $article): array => [
                ...$this->serializeArticle($article),
                'source' => $article->source_slug,
                'journalName' => $article->journal?->name,
                'issueTitle' => $article->issue?->title,
                'sourceName' => $this->sources->find($article->source_slug)['name'] ?? $article->source_slug,
                'year' => $article->issue?->year,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJournal(CatalogJournal $journal): array
    {
        return [
            'id' => $journal->compound_id,
            'source' => $journal->source_slug,
            'remoteId' => $journal->remote_id,
            'name' => $journal->name,
            'description' => $journal->description,
            'issn' => $journal->issn,
            'url' => $journal->url,
            'thumbnailUrl' => $journal->thumbnail_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIssue(CatalogIssue $issue): array
    {
        return [
            'id' => $issue->compound_id,
            'journalId' => $issue->journal_compound_id,
            'remoteId' => $issue->remote_id,
            'title' => $issue->title,
            'volume' => $issue->volume,
            'number' => $issue->number,
            'year' => $issue->year,
            'description' => $issue->description,
            'publishedAt' => $issue->published_at?->toIso8601String(),
            'coverUrl' => $issue->cover_url,
            'url' => $issue->url,
            'pdf' => $issue->pdf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(CatalogArticle $article, bool $includeDetail = false): array
    {
        $payload = [
            'id' => $article->compound_id,
            'journalId' => $article->journal_compound_id,
            'remoteId' => $article->remote_id,
            'issueId' => $article->issue_compound_id,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'authors' => $article->authors ?? [],
            'authorsString' => $article->authors_string,
            'abstract' => $article->abstract,
            'keywords' => $article->keywords ?? [],
            'doi' => $article->doi,
            'pages' => $article->pages,
            'publishedAt' => $article->published_at?->toIso8601String(),
            'url' => $article->url,
            'pdf' => $article->pdf,
        ];

        if (! $includeDetail) {
            return $payload;
        }

        return array_merge($payload, [
            'citations' => $article->citations ?? [],
            'licenseUrl' => $article->license_url,
            'references' => $article->references ?? [],
            'section' => $article->section,
        ]);
    }

    private function extractJournalCompoundIdFromIssue(string $issueCompoundId): string
    {
        $parts = explode(':', $issueCompoundId, 3);

        return implode(':', array_slice($parts, 0, 2));
    }
}

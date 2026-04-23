<?php

namespace App\Services\Ojs\Drivers;

interface OjsDriver
{
    /**
     * @return array<string, mixed>
     */
    public function describeSource(array $source): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJournals(array $source): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(array $source, string $journalId): array;

    /**
     * @return array<string, mixed>
     */
    public function getIssue(array $source, string $journalId, string $issueId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listArticles(array $source, string $journalId, string $issueId): array;

    /**
     * @return array<string, mixed>
     */
    public function getArticle(array $source, string $journalId, string $articleId): array;
}

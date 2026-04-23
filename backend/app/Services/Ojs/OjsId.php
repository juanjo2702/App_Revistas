<?php

namespace App\Services\Ojs;

use App\Exceptions\BridgeException;

class OjsId
{
    public static function journal(string $sourceSlug, string|int $journalId): string
    {
        return implode(':', [$sourceSlug, $journalId]);
    }

    public static function issue(string $sourceSlug, string|int $journalId, string|int $issueId): string
    {
        return implode(':', [$sourceSlug, $journalId, $issueId]);
    }

    public static function article(string $sourceSlug, string|int $journalId, string|int $submissionId): string
    {
        return implode(':', [$sourceSlug, $journalId, $submissionId]);
    }

    /**
     * @return array{source:string,journalId:string}
     */
    public static function parseJournal(string $compoundId): array
    {
        $parts = explode(':', $compoundId, 2);

        if (count($parts) !== 2 || in_array('', $parts, true)) {
            throw new BridgeException('Invalid journal identifier.', 422);
        }

        return [
            'source' => $parts[0],
            'journalId' => $parts[1],
        ];
    }

    /**
     * @return array{source:string,journalId:string,issueId:string}
     */
    public static function parseIssue(string $compoundId): array
    {
        $parts = explode(':', $compoundId, 3);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new BridgeException('Invalid issue identifier.', 422);
        }

        return [
            'source' => $parts[0],
            'journalId' => $parts[1],
            'issueId' => $parts[2],
        ];
    }

    /**
     * @return array{source:string,journalId:string,submissionId:string}
     */
    public static function parseArticle(string $compoundId): array
    {
        $parts = explode(':', $compoundId, 3);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new BridgeException('Invalid article identifier.', 422);
        }

        return [
            'source' => $parts[0],
            'journalId' => $parts[1],
            'submissionId' => $parts[2],
        ];
    }
}

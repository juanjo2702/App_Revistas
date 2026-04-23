<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BridgeException;
use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Ojs\OjsBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OjsBridgeController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalog,
        private readonly OjsBridgeService $bridge,
    )
    {
    }

    public function sources(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->listSources(),
        ]);
    }

    public function journals(Request $request): JsonResponse
    {
        $source = (string) $request->query('source', '');

        if ($source === '') {
            return response()->json([
                'message' => 'The source query parameter is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->handle(fn () => response()->json([
            'data' => $this->catalog->listJournals($source),
            'meta' => ['source' => $source],
        ]));
    }

    public function issues(string $journalId): JsonResponse
    {
        return $this->handle(fn () => response()->json([
            'data' => $this->catalog->listIssues($journalId),
            'meta' => ['journalId' => $journalId],
        ]));
    }

    public function articles(string $issueId): JsonResponse
    {
        return $this->handle(fn () => response()->json([
            'data' => $this->catalog->listArticles($issueId),
            'meta' => ['issueId' => $issueId],
        ]));
    }

    public function article(string $articleId): JsonResponse
    {
        return $this->handle(fn () => response()->json([
            'data' => $this->catalog->getArticle($articleId),
        ]));
    }

    public function issuePdf(Request $request, string $issueId)
    {
        $disposition = $request->query('disposition', 'inline');

        try {
            return $this->bridge->streamIssuePdf(
                issueCompoundId: $issueId,
                request: $request,
                disposition: $disposition === 'attachment' ? 'attachment' : 'inline',
            );
        } catch (BridgeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $source = $request->query('source');
        $year = $request->query('year');

        return $this->handle(fn () => response()->json([
            'data' => $this->catalog->searchArticles(
                query: $query,
                sourceSlug: is_string($source) && $source !== '' ? $source : null,
                year: is_numeric($year) ? (int) $year : null,
            ),
            'meta' => [
                'query' => $query,
                'source' => $source,
                'year' => is_numeric($year) ? (int) $year : null,
            ],
        ]));
    }

    public function pdf(Request $request, string $articleId)
    {
        $disposition = $request->query('disposition', 'inline');

        try {
            return $this->bridge->streamArticlePdf(
                articleCompoundId: $articleId,
                request: $request,
                disposition: $disposition === 'attachment' ? 'attachment' : 'inline',
            );
        } catch (BridgeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }
    }

    private function handle(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (BridgeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status());
        }
    }
}

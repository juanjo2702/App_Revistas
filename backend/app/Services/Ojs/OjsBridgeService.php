<?php

namespace App\Services\Ojs;

use App\Exceptions\BridgeException;
use App\Services\Ojs\Drivers\OjsDriverRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OjsBridgeService
{
    public function __construct(
        private readonly OjsSourceRegistry $sources,
        private readonly OjsDriverRegistry $drivers,
        private readonly OjsHttpClient $http,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSources(): array
    {
        return $this->sources->all()
            ->map(fn (array $source): array => $this->drivers->forSource($source)->describeSource($source))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJournals(string $sourceSlug): array
    {
        $source = $this->sources->find($sourceSlug);

        return $this->drivers->forSource($source)->listJournals($source);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(string $journalCompoundId): array
    {
        $journalRef = OjsId::parseJournal($journalCompoundId);
        $source = $this->sources->find($journalRef['source']);

        return $this->drivers->forSource($source)->listIssues($source, $journalRef['journalId']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getIssue(string $issueCompoundId): array
    {
        $issueRef = OjsId::parseIssue($issueCompoundId);
        $source = $this->sources->find($issueRef['source']);

        return $this->drivers->forSource($source)->getIssue($source, $issueRef['journalId'], $issueRef['issueId']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listArticles(string $issueCompoundId): array
    {
        $issueRef = OjsId::parseIssue($issueCompoundId);
        $source = $this->sources->find($issueRef['source']);

        return $this->drivers->forSource($source)->listArticles($source, $issueRef['journalId'], $issueRef['issueId']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getArticle(string $articleCompoundId): array
    {
        $articleRef = OjsId::parseArticle($articleCompoundId);
        $source = $this->sources->find($articleRef['source']);

        return $this->drivers->forSource($source)->getArticle($source, $articleRef['journalId'], $articleRef['submissionId']);
    }

    public function streamIssuePdf(string $issueCompoundId, Request $request, string $disposition = 'inline'): StreamedResponse
    {
        $issue = $this->getIssue($issueCompoundId);
        $asset = data_get($issue, 'pdf');

        if (! is_array($asset)) {
            throw new BridgeException('The requested issue does not expose a PDF asset.', 404);
        }

        $issueRef = OjsId::parseIssue($issueCompoundId);
        $source = $this->sources->find($issueRef['source']);

        return $this->streamPdfAsset($source, $asset, $request, $disposition, sprintf('%s.pdf', $issueRef['issueId']));
    }

    public function streamArticlePdf(string $articleCompoundId, Request $request, string $disposition = 'inline'): StreamedResponse
    {
        $article = $this->getArticle($articleCompoundId);
        $asset = data_get($article, 'pdf');

        if (! is_array($asset)) {
            throw new BridgeException('The requested article does not expose a PDF asset.', 404);
        }

        $articleRef = OjsId::parseArticle($articleCompoundId);
        $source = $this->sources->find($articleRef['source']);

        return $this->streamPdfAsset($source, $asset, $request, $disposition, sprintf('%s.pdf', $articleRef['submissionId']));
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    private function streamPdfAsset(array $source, array $asset, Request $request, string $disposition, string $fallbackFilename): StreamedResponse
    {
        $pdfUrl = data_get($asset, 'url');

        if (! is_string($pdfUrl) || $pdfUrl === '') {
            throw new BridgeException('The requested PDF asset is missing its upstream URL.', 404);
        }

        $response = $this->http->stream($source, $pdfUrl, $request->header('Range'));
        $stream = $response->toPsrResponse()->getBody();
        $filename = data_get($asset, 'filename') ?: $fallbackFilename;
        $safeFilename = str_replace('"', '', (string) $filename);

        $headers = [
            'Content-Type' => $response->header('Content-Type', 'application/pdf'),
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition === 'attachment' ? 'attachment' : 'inline', $safeFilename),
        ];

        foreach (['Accept-Ranges', 'Content-Length', 'Content-Range'] as $header) {
            if ($value = $response->header($header)) {
                $headers[$header] = $value;
            }
        }

        return response()->stream(function () use ($stream): void {
            while (! $stream->eof()) {
                echo $stream->read(16 * 1024);
                flush();
            }
        }, $response->status(), $headers);
    }
}

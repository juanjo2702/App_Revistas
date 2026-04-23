<?php

namespace App\Services\Ojs\Drivers;

use App\Exceptions\BridgeException;
use App\Services\Ojs\OjsHttpClient;
use App\Services\Ojs\OjsId;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicOjs34Driver implements OjsDriver
{
    public function __construct(private readonly OjsHttpClient $http)
    {
    }

    public function describeSource(array $source): array
    {
        return [
            'slug' => $source['slug'],
            'name' => $source['name'],
            'driver' => $source['driver'] ?? 'public_ojs_34',
            'baseUrl' => rtrim((string) ($source['base_url'] ?? ''), '/'),
            'apiBaseUrl' => null,
        ];
    }

    public function listJournals(array $source): array
    {
        $journalUrl = $this->requiredUrl($source, 'journal_url');

        return [$this->parseJournalPage($source, $journalUrl)];
    }

    public function listIssues(array $source, string $journalId): array
    {
        return array_map(
            fn (array $issue): array => $this->getIssue($source, $journalId, (string) $issue['remoteId']),
            $this->parseArchivePage($source),
        );
    }

    public function getIssue(array $source, string $journalId, string $issueId): array
    {
        $cacheKey = sprintf('public-ojs34-issue:%s:%s:%s', $source['slug'], $journalId, $issueId);

        return Cache::remember($cacheKey, config('ojs.http.cache_ttl_seconds'), function () use ($source, $journalId, $issueId): array {
            $archiveIssue = collect($this->parseArchivePage($source))
                ->firstWhere('remoteId', $issueId);

            $issueUrl = is_array($archiveIssue)
                ? (string) ($archiveIssue['url'] ?? '')
                : $this->http->absoluteUrl($source, sprintf('index.php/%s/issue/view/%s', $journalId, $issueId));

            if ($issueUrl === '') {
                throw new BridgeException('The requested issue was not found in the upstream OJS source.', 404);
            }

            return $this->parseIssuePage($source, $journalId, $issueId, $issueUrl, is_array($archiveIssue) ? $archiveIssue : null);
        });
    }

    public function listArticles(array $source, string $journalId, string $issueId): array
    {
        return Arr::wrap($this->getIssue($source, $journalId, $issueId)['_articles'] ?? []);
    }

    public function getArticle(array $source, string $journalId, string $articleId): array
    {
        $cacheKey = sprintf('public-ojs34-article:%s:%s:%s', $source['slug'], $journalId, $articleId);

        return Cache::remember($cacheKey, config('ojs.http.cache_ttl_seconds'), function () use ($source, $journalId, $articleId): array {
            $articleUrl = $this->http->absoluteUrl($source, sprintf('index.php/%s/article/view/%s', $journalId, $articleId));
            $html = $this->http->getHtml($source, $articleUrl);
            [$xpath] = $this->loadDom($html);
            $meta = $this->metaMap($xpath);

            $issueHref = $this->firstAttribute($xpath, '//a[contains(@href, "/issue/view/")]', 'href');
            $issueRemoteId = $this->extractIdFromUrl($issueHref, '#/issue/view/(\d+)#');
            $authors = $meta['citation_author'] ?? $meta['DC.Creator.PersonalName'] ?? [];
            $cleanAuthors = array_values(array_filter(array_map([$this, 'cleanText'], $authors)));
            $keywords = $meta['citation_keywords'] ?? $meta['DC.Subject'] ?? [];
            $title = $this->firstMeta($meta, 'citation_title')
                ?? $this->firstMeta($meta, 'DC.Title')
                ?? $this->textOf($xpath, '//h1')
                ?? 'Untitled article';
            $pdfUrl = $this->firstMeta($meta, 'citation_pdf_url')
                ?? $this->deriveGalleyDownloadUrl(
                    $this->firstAttribute($xpath, '//a[contains(@href, concat("/article/view/", "'.$articleId.'", "/"))]', 'href')
                );

            return [
                'id' => OjsId::article($source['slug'], $journalId, $articleId),
                'journalId' => OjsId::journal($source['slug'], $journalId),
                'remoteId' => $articleId,
                'issueId' => $issueRemoteId ? OjsId::issue($source['slug'], $journalId, $issueRemoteId) : null,
                'title' => $title,
                'subtitle' => null,
                'authors' => $cleanAuthors,
                'authorsString' => $cleanAuthors !== [] ? implode(', ', $cleanAuthors) : null,
                'abstract' => $this->cleanText($this->firstMeta($meta, 'citation_abstract') ?? $this->firstMeta($meta, 'DC.Description')),
                'keywords' => array_values(array_filter(array_map([$this, 'cleanText'], $keywords))),
                'doi' => $this->normalizeDoi($this->firstMeta($meta, 'citation_doi') ?? $this->firstMeta($meta, 'DC.Identifier.DOI')),
                'pages' => $this->buildPages(
                    $this->firstMeta($meta, 'citation_firstpage'),
                    $this->firstMeta($meta, 'citation_lastpage'),
                    $this->firstMeta($meta, 'DC.Identifier.pageNumber'),
                ),
                'publishedAt' => $this->normalizeDate(
                    $this->firstMeta($meta, 'citation_date')
                    ?? $this->firstMeta($meta, 'DC.Date.created')
                    ?? $this->extractLabeledValue($xpath, 'Publicado')
                ),
                'url' => $this->firstMeta($meta, 'citation_abstract_html_url')
                    ?? $this->firstMeta($meta, 'DC.Identifier.URI')
                    ?? $articleUrl,
                'pdf' => $this->buildPdfAsset($source, $title, $pdfUrl, 'article'),
                'citations' => [],
                'licenseUrl' => $this->firstAttribute($xpath, '//a[contains(@href, "creativecommons.org")]', 'href'),
                'references' => [],
                'section' => $this->extractLabeledValue($xpath, 'Seccion')
                    ?? $this->extractLabeledValue($xpath, 'Sección')
                    ?? $this->firstMeta($meta, 'DC.Type.articleType'),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJournalPage(array $source, string $journalUrl): array
    {
        $html = $this->http->getHtml($source, $journalUrl);
        [$xpath] = $this->loadDom($html);
        $remoteId = $this->extractJournalRemoteId($journalUrl);

        return [
            'id' => OjsId::journal($source['slug'], $remoteId),
            'source' => $source['slug'],
            'remoteId' => $remoteId,
            'name' => $this->textOf($xpath, '//h1')
                ?? $this->trimTitle($this->textOf($xpath, '//title'))
                ?? $source['name'],
            'description' => $this->extractJournalDescription($xpath),
            'issn' => $this->extractIssn($xpath),
            'url' => $journalUrl,
            'thumbnailUrl' => $this->firstAttribute($xpath, '//img[contains(@src, "pageHeaderLogoImage")]', 'src'),
            'apiHref' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseArchivePage(array $source): array
    {
        $html = $this->http->getHtml($source, $this->requiredUrl($source, 'archive_url'));
        [$xpath] = $this->loadDom($html);
        $nodes = $xpath->query('//a[contains(@class, "title") and contains(@href, "/issue/view/")]');
        $issues = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $href = (string) $node->getAttribute('href');
            $remoteId = $this->extractIdFromUrl($href, '#/issue/view/(\d+)#');

            if ($remoteId === null || isset($issues[$remoteId])) {
                continue;
            }

            $container = $node->parentNode instanceof DOMElement ? $node->parentNode : null;
            $title = $this->cleanText($node->textContent);
            $fullText = $container ? $this->cleanText($container->textContent) : $title;
            $volumeLine = $this->match($fullText ?? '', '/Vol\.\s*\d+\s*N[úu]m\.\s*\d+\s*\(\d{4}\)/u');
            $description = $fullText ?? '';

            if ($title !== null) {
                $description = trim(str_replace($title, '', $description));
            }

            if ($volumeLine !== null) {
                $description = trim(str_replace($volumeLine, '', $description));
            }

            $issues[$remoteId] = [
                'remoteId' => $remoteId,
                'title' => $volumeLine ? trim($volumeLine.': '.$title) : ($title ?? 'Numero'),
                'description' => $description !== '' ? $description : null,
                'url' => $href,
            ];
        }

        return array_values($issues);
    }

    /**
     * @param  array<string, mixed>|null  $archiveIssue
     * @return array<string, mixed>
     */
    private function parseIssuePage(array $source, string $journalId, string $issueId, string $issueUrl, ?array $archiveIssue): array
    {
        $html = $this->http->getHtml($source, $issueUrl);
        [$xpath] = $this->loadDom($html);
        $title = $this->textOf($xpath, '//h1') ?? ($archiveIssue['title'] ?? 'Numero');
        [$volume, $number, $year] = $this->parseIssueIdentification($title);

        return [
            'id' => OjsId::issue($source['slug'], $journalId, $issueId),
            'journalId' => OjsId::journal($source['slug'], $journalId),
            'remoteId' => $issueId,
            'title' => $title,
            'volume' => $volume,
            'number' => $number,
            'year' => $year,
            'publishedAt' => $this->normalizeDate($this->extractPublishedLabel($xpath)),
            'description' => $this->extractIssueDescription($xpath) ?? ($archiveIssue['description'] ?? null),
            'coverUrl' => $this->firstAttribute(
                $xpath,
                '//img[not(contains(@src, "pageHeaderLogoImage"))][not(contains(@src, "creativecommons"))][1]',
                'src',
            ),
            'url' => $issueUrl,
            'pdf' => $this->buildPdfAsset(
                $source,
                $title,
                $this->deriveGalleyDownloadUrl(
                    $this->firstAttribute($xpath, '//a[contains(@href, "/issue/view/") and contains(., "PDF completo")]', 'href')
                ),
                'issue',
            ),
            '_articles' => $this->extractIssueArticles($source, $journalId, $issueId, $xpath),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractIssueArticles(array $source, string $journalId, string $issueId, DOMXPath $xpath): array
    {
        $cards = $xpath->query('//a[starts-with(@id, "article-") and contains(@href, "/article/view/")]');
        $articles = [];

        if ($cards === false) {
            return [];
        }

        foreach ($cards as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $articleId = $this->extractIdFromUrl((string) $anchor->getAttribute('href'), '#/article/view/(\d+)#');

            if ($articleId === null || isset($articles[$articleId])) {
                continue;
            }

            $container = $anchor->parentNode instanceof DOMElement ? $anchor->parentNode : null;
            $text = $container ? $this->cleanText($container->textContent) : null;
            $authors = $this->match($text ?? '', '/([^\r\n]+)\(Autor\/a\)/u');
            $articleTitle = $this->cleanText($anchor->textContent) ?? 'Articulo';
            $galleyHref = null;

            if ($container) {
                foreach ($container->getElementsByTagName('a') as $link) {
                    if (! $link instanceof DOMElement) {
                        continue;
                    }

                    $href = (string) $link->getAttribute('href');

                    if ($href !== '' && preg_match('#/article/view/'.$articleId.'/\d+$#', $href) === 1) {
                        $galleyHref = $href;
                        break;
                    }
                }
            }

            $articles[$articleId] = [
                'id' => OjsId::article($source['slug'], $journalId, $articleId),
                'journalId' => OjsId::journal($source['slug'], $journalId),
                'remoteId' => $articleId,
                'issueId' => OjsId::issue($source['slug'], $journalId, $issueId),
                'title' => $articleTitle,
                'subtitle' => null,
                'authors' => $this->splitAuthors($authors),
                'authorsString' => $authors ? trim($authors) : null,
                'abstract' => null,
                'keywords' => [],
                'doi' => null,
                'pages' => $this->match($text ?? '', '/\b\d+\s*-\s*\d+\b/u'),
                'publishedAt' => null,
                'url' => (string) $anchor->getAttribute('href'),
                'pdf' => $this->buildPdfAsset($source, $articleTitle, $this->deriveGalleyDownloadUrl($galleyHref), 'article'),
            ];
        }

        return array_values($articles);
    }

    /**
     * @return array{0:?string,1:?string,2:?int}
     */
    private function parseIssueIdentification(string $title): array
    {
        if (preg_match('/Vol\.\s*([0-9A-Za-z.-]+)\s*N[úu]m\.\s*([0-9A-Za-z.-]+)\s*\((\d{4})\)/u', $title, $matches) !== 1) {
            return [null, null, null];
        }

        return [$matches[1], $matches[2], (int) $matches[3]];
    }

    private function requiredUrl(array $source, string $key): string
    {
        $url = $source[$key] ?? null;

        if (! is_string($url) || $url === '') {
            throw new BridgeException("The OJS source is missing the required [{$key}] URL.", 500);
        }

        return $url;
    }

    /**
     * @return array{0:DOMXPath,1:DOMDocument}
     */
    private function loadDom(string $html): array
    {
        libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        libxml_clear_errors();

        return [$xpath, $document];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function metaMap(DOMXPath $xpath): array
    {
        $map = [];
        $nodes = $xpath->query('//meta[@name and @content]');

        if ($nodes === false) {
            return $map;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $name = trim((string) $node->getAttribute('name'));
            $content = html_entity_decode((string) $node->getAttribute('content'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($name === '' || trim($content) === '') {
                continue;
            }

            $map[$name] ??= [];
            $map[$name][] = trim($content);
        }

        return $map;
    }

    private function firstMeta(array $meta, string $name): ?string
    {
        return isset($meta[$name][0]) ? $this->cleanText($meta[$name][0]) : null;
    }

    private function firstAttribute(DOMXPath $xpath, string $query, string $attribute): ?string
    {
        $node = $xpath->query($query)?->item(0);

        if (! $node instanceof DOMElement) {
            return null;
        }

        $value = trim((string) $node->getAttribute($attribute));

        return $value !== '' ? $value : null;
    }

    private function textOf(DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query)?->item(0);

        return $node instanceof DOMNode ? $this->cleanText($node->textContent) : null;
    }

    private function extractJournalRemoteId(string $journalUrl): string
    {
        $path = parse_url($journalUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return 'journal';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        return (string) end($segments);
    }

    private function extractJournalDescription(DOMXPath $xpath): ?string
    {
        $headings = $xpath->query('//h2');

        if ($headings === false) {
            return null;
        }

        $heading = null;

        foreach ($headings as $candidate) {
            if (! $candidate instanceof DOMNode) {
                continue;
            }

            if ($this->normalizeLabel($candidate->textContent) === 'sobre la revista') {
                $heading = $candidate;
                break;
            }
        }

        if (! $heading instanceof DOMNode) {
            return null;
        }

        $parts = [];

        for ($node = $heading->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['h1', 'h2', 'h3'], true)) {
                break;
            }

            if ($node instanceof DOMElement && strtolower($node->tagName) === 'p') {
                $text = $this->cleanText($node->textContent);

                if ($text !== null) {
                    $parts[] = $text;
                }
            }
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    private function extractIssn(DOMXPath $xpath): ?string
    {
        $footer = $this->textOf($xpath, '//footer');

        if ($footer === null) {
            return null;
        }

        $issn = $this->match($footer, '/ISSN\s+([^\s|]+)/u', 1);

        return $issn === '####' ? null : $issn;
    }

    private function trimTitle(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        return trim(preg_replace('/\|\s*G-news UNITEPC$/u', '', $title) ?? $title);
    }

    private function extractIssueDescription(DOMXPath $xpath): ?string
    {
        $heading = $xpath->query('//h1')?->item(0);

        if (! $heading instanceof DOMNode) {
            return null;
        }

        $parts = [];

        for ($node = $heading->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof DOMElement && strtolower($node->tagName) === 'h2') {
                break;
            }

            if ($node instanceof DOMElement && strtolower($node->tagName) === 'p') {
                $text = $this->cleanText($node->textContent);

                if ($text !== null) {
                    $parts[] = $text;
                }
            }
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    private function extractPublishedLabel(DOMXPath $xpath): ?string
    {
        $value = $this->extractLabeledValue($xpath, 'Publicado');

        if ($value !== null) {
            return $value;
        }

        $node = $xpath->query('//span[contains(., "Publicado:")]')?->item(0);

        if (! $node instanceof DOMNode) {
            return null;
        }

        $text = $this->cleanText($node->parentNode?->textContent ?? '');

        return $text ? trim(str_replace('Publicado:', '', $text)) : null;
    }

    private function extractLabeledValue(DOMXPath $xpath, string $label): ?string
    {
        $nodes = $xpath->query('//div[contains(@class, "item")]//*[self::h2 or self::h3 or self::div]');

        if ($nodes === false) {
            return null;
        }

        $target = $this->normalizeLabel($label);
        $node = null;

        foreach ($nodes as $candidate) {
            if (! $candidate instanceof DOMNode) {
                continue;
            }

            if ($this->normalizeLabel($candidate->textContent) === $target) {
                $node = $candidate;
                break;
            }
        }

        if (! $node instanceof DOMNode) {
            return null;
        }

        $container = $node->parentNode instanceof DOMNode ? $node->parentNode : null;
        $text = $container ? $this->cleanText($container->textContent) : null;

        if ($text === null) {
            return null;
        }

        $cleanLabel = $this->cleanText($label) ?? $label;
        $nodeLabel = $this->cleanText($node->textContent);
        $candidates = array_values(array_unique(array_filter([
            $cleanLabel,
            Str::ascii($cleanLabel),
            $nodeLabel,
            $nodeLabel ? Str::ascii($nodeLabel) : null,
        ])));

        foreach ($candidates as $candidate) {
            $text = preg_replace('/^'.preg_quote($candidate, '/').'\s*/iu', '', $text) ?? $text;
        }

        return trim($text);
    }

    private function buildPages(?string $firstPage, ?string $lastPage, ?string $pageRange): ?string
    {
        if ($pageRange !== null && trim($pageRange) !== '') {
            return trim($pageRange);
        }

        if ($firstPage !== null && $lastPage !== null) {
            return trim($firstPage).'-'.trim($lastPage);
        }

        return $firstPage !== null ? trim($firstPage) : null;
    }

    private function normalizeDoi(?string $doi): ?string
    {
        if ($doi === null || trim($doi) === '') {
            return null;
        }

        return preg_replace('#^https?://(dx\.)?doi\.org/#i', '', trim($doi)) ?: trim($doi);
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(str_replace('/', '-', trim($value)))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function deriveGalleyDownloadUrl(?string $viewUrl): ?string
    {
        if (! is_string($viewUrl) || $viewUrl === '') {
            return null;
        }

        return preg_replace('#/(article|issue)/view/(\d+)/(\d+)$#', '/$1/download/$2/$3', $viewUrl) ?: $viewUrl;
    }

    private function buildPdfAsset(array $source, string $title, ?string $url, string $kind): ?array
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $filename = Str::slug(Str::limit($title, 80, ''), '-');
        $filename = $filename !== '' ? $filename : $kind;

        return [
            'url' => $this->http->absoluteUrl($source, $url),
            'mimeType' => 'application/pdf',
            'filename' => $filename.'.pdf',
            'downloadable' => true,
        ];
    }

    private function extractIdFromUrl(?string $url, string $pattern): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_match($pattern, $url, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @return array<int, string>
     */
    private function splitAuthors(?string $authors): array
    {
        if ($authors === null || trim($authors) === '') {
            return [];
        }

        return array_values(array_filter(array_map([$this, 'cleanText'], preg_split('/,\s*/u', $authors) ?: [])));
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);
        $decoded = preg_replace('/\s+/u', ' ', strip_tags($decoded)) ?? $decoded;
        $decoded = trim($decoded);

        return $decoded !== '' ? $decoded : null;
    }

    private function match(string $subject, string $pattern, int $group = 0): ?string
    {
        return preg_match($pattern, $subject, $matches) === 1 ? $matches[$group] : null;
    }

    private function normalizeLabel(string $value): string
    {
        return Str::of($this->cleanText($value) ?? '')
            ->ascii()
            ->lower()
            ->value();
    }
}

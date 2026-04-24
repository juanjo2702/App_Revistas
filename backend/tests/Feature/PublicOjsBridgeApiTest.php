<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicOjsBridgeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ojs.http.cache_ttl_seconds', 1);
        config()->set('ojs.sources', [[
            'slug' => 'g-news',
            'name' => 'G-News UNITEPC',
            'driver' => 'public_ojs_34',
            'base_url' => 'https://g-news.example/revista',
            'api_base_url' => null,
            'journal_url' => 'https://g-news.example/revista/index.php/revista',
            'archive_url' => 'https://g-news.example/revista/index.php/revista/issue/archive',
            'oai_base_url' => 'https://g-news.example/revista/index.php/revista/oai',
            'enabled' => true,
            'token' => null,
        ]]);
    }

    public function test_it_syncs_a_public_ojs_34_source_without_credentials(): void
    {
        Http::fake([
            'https://g-news.example/revista/index.php/revista' => Http::response($this->fixture('g-news-journal.html')),
            'https://g-news.example/revista/index.php/revista/issue/archive' => Http::response($this->fixture('g-news-archive.html')),
            'https://g-news.example/revista/index.php/revista/issue/view/1' => Http::response($this->fixture('g-news-issue-1.html')),
            'https://g-news.example/revista/index.php/revista/issue/view/2' => Http::response($this->fixture('g-news-issue-2.html')),
            'https://g-news.example/revista/index.php/revista/article/view/25' => Http::response($this->fixture('g-news-article-25.html')),
        ]);

        $sources = $this->getJson('/api/v1/sources');
        $sources
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'g-news')
            ->assertJsonPath('data.0.driver', 'public_ojs_34')
            ->assertJsonPath('data.0.apiBaseUrl', null);

        $journals = $this->getJson('/api/v1/journals?source=g-news');
        $journals
            ->assertOk()
            ->assertJsonPath('data.0.id', 'g-news:revista')
            ->assertJsonPath('data.0.name', 'G-news UNITEPC');

        $issues = $this->getJson('/api/v1/journals/g-news:revista/issues');
        $issues
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'g-news:revista:2')
            ->assertJsonPath('data.0.pdf.filename', 'vol-1-num-2-2025-avances.pdf');

        $articles = $this->getJson('/api/v1/issues/g-news:revista:2/articles');
        $articles
            ->assertOk()
            ->assertJsonPath('data.0.id', 'g-news:revista:25')
            ->assertJsonPath('data.0.authorsString', 'Mauricio Quiroz Lafuente, Leonardo Zurita Maldonado');

        $article = $this->getJson('/api/v1/articles/g-news:revista:25');
        $article
            ->assertOk()
            ->assertJsonPath('data.id', 'g-news:revista:25')
            ->assertJsonPath('data.section', 'Artículos Científicos')
            ->assertJsonPath('data.pdf.url', 'https://g-news.example/revista/index.php/revista/article/download/25/3');
    }

    public function test_it_streams_issue_pdf_from_a_public_ojs_source(): void
    {
        Http::fake([
            'https://g-news.example/revista/index.php/revista/issue/archive' => Http::response($this->fixture('g-news-archive.html')),
            'https://g-news.example/revista/index.php/revista/issue/view/1' => Http::response($this->fixture('g-news-issue-1.html')),
            'https://g-news.example/revista/index.php/revista/issue/view/2' => Http::response($this->fixture('g-news-issue-2.html')),
            'https://g-news.example/revista/index.php/revista/issue/download/2/2' => Http::response(
                body: 'issue pdf body',
                status: 206,
                headers: [
                    'Content-Type' => 'application/pdf',
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => 'bytes 0-13/100',
                    'Content-Length' => '14',
                ],
            ),
        ]);

        $response = $this->call('GET', '/api/v1/issues/g-news:revista:2/pdf?disposition=attachment', server: [
            'HTTP_RANGE' => 'bytes=0-13',
        ]);

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Disposition', 'attachment; filename="vol-1-num-2-2025-avances.pdf"')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-13/100');

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://g-news.example/revista/index.php/revista/issue/download/2/2'
                && $request->hasHeader('Range', 'bytes=0-13');
        });
    }

    public function test_it_syncs_a_public_multi_journal_ojs_source_without_credentials(): void
    {
        config()->set('ojs.sources', [[
            'slug' => 'investigacion',
            'name' => 'Investigacion UNITEPC',
            'driver' => 'public_ojs_34',
            'base_url' => 'https://investigacion.example/revista',
            'site_url' => 'https://investigacion.example/revista/',
            'api_base_url' => null,
            'enabled' => true,
            'token' => null,
        ]]);

        Http::fake([
            'https://investigacion.example/revista/' => Http::response($this->fixture('investigacion-site-index.html')),
            'https://investigacion.example/revista/index.php/revista-unitepc/issue/archive' => Http::response($this->fixture('investigacion-revista-unitepc-archive.html')),
            'https://investigacion.example/revista/index.php/revista-unitepc/issue/view/52' => Http::response($this->fixture('investigacion-revista-unitepc-issue-52.html')),
            'https://investigacion.example/revista/index.php/revista-unitepc/article/view/286' => Http::response($this->fixture('investigacion-revista-unitepc-article-286.html')),
            'https://investigacion.example/revista/index.php/enfermeria/issue/archive' => Http::response($this->fixture('investigacion-enfermeria-archive.html')),
            'https://investigacion.example/revista/index.php/enfermeria/issue/view/61' => Http::response($this->fixture('investigacion-enfermeria-issue-61.html')),
            'https://investigacion.example/revista/index.php/enfermeria/article/view/401' => Http::response($this->fixture('investigacion-enfermeria-article-401.html')),
        ]);

        $sources = $this->getJson('/api/v1/sources');
        $sources
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'investigacion')
            ->assertJsonPath('data.0.driver', 'public_ojs_34');

        $journals = $this->getJson('/api/v1/journals?source=investigacion');
        $journals
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'investigacion:enfermeria')
            ->assertJsonPath('data.1.id', 'investigacion:revista-unitepc');

        $issues = $this->getJson('/api/v1/journals/investigacion:revista-unitepc/issues');
        $issues
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'investigacion:revista-unitepc:52')
            ->assertJsonPath('data.0.pdf.url', 'https://investigacion.example/revista/index.php/revista-unitepc/issue/download/52/44');

        $articles = $this->getJson('/api/v1/issues/investigacion:revista-unitepc:52/articles');
        $articles
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'investigacion:revista-unitepc:286')
            ->assertJsonPath('data.0.pdf.url', 'https://investigacion.example/revista/index.php/revista-unitepc/article/download/286/275');

        $article = $this->getJson('/api/v1/articles/investigacion:revista-unitepc:286');
        $article
            ->assertOk()
            ->assertJsonPath('data.id', 'investigacion:revista-unitepc:286')
            ->assertJsonPath('data.section', 'Nota Editorial')
            ->assertJsonPath('data.doi', '10.36716/unitepc.v12i2.286');
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__.'/../Fixtures/'.$name) ?: '';
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OjsBridgeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ojs.http.cache_ttl_seconds', 1);
        config()->set('ojs.sources', [[
            'slug' => 'investigacion',
            'name' => 'Familia de Revistas Científicas UNITEPC',
            'driver' => 'rest_v1',
            'base_url' => 'https://investigacion.example/ojs',
            'api_base_url' => 'https://investigacion.example/ojs/index.php/_/api/v1',
            'enabled' => true,
            'token' => null,
        ]]);
    }

    public function test_it_returns_active_sources(): void
    {
        $response = $this->getJson('/api/v1/sources');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'investigacion')
            ->assertJsonPath('data.0.name', 'Familia de Revistas Científicas UNITEPC');
    }

    public function test_it_handles_mobile_cors_preflight_requests(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/sources', server: [
            'HTTP_ORIGIN' => 'capacitor://localhost',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Range, Content-Type',
        ]);

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'capacitor://localhost')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With, Range');
    }

    public function test_it_allows_https_localhost_origin_for_android_webview_requests(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/sources', server: [
            'HTTP_ORIGIN' => 'https://localhost',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://localhost');
    }

    public function test_it_normalizes_journals_issues_articles_and_detail(): void
    {
        Http::fake([
            'https://investigacion.example/ojs/index.php/_/api/v1/contexts' => Http::response([
                'items' => [[
                    'id' => 10,
                    'name' => ['es_ES' => 'Revista Cientifica UNITEPC'],
                    'description' => ['es_ES' => 'Revista principal'],
                    'path' => 'revista-unitepc',
                    '_href' => 'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10',
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10/issues' => Http::response([
                'items' => [[
                    'id' => 32,
                    'volume' => 8,
                    'number' => 1,
                    'year' => 2024,
                    'issueIdentification' => 'Vol. 8 No. 1 (2024)',
                    'datePublished' => '2024-01-20',
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10/submissions/169' => Http::response([
                'id' => 169,
                'datePublished' => '2024-01-20',
                'currentPublicationId' => 501,
                'publications' => [[
                    'id' => 501,
                    'issueId' => 32,
                    'title' => ['es_ES' => 'Articulo de prueba'],
                    'abstract' => ['es_ES' => 'Resumen corto'],
                    'authors' => [[
                        'givenName' => 'Ana',
                        'familyName' => 'Perez',
                    ]],
                    'authorsString' => 'Ana Perez',
                    '_href' => 'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/submissions/169/publications/501',
                    'galleys' => [[
                        'label' => 'PDF',
                        'urlPublished' => 'https://investigacion.example/ojs/article/download/169/demo.pdf',
                        'submissionFile' => [
                            'name' => 'demo.pdf',
                            'mimetype' => 'application/pdf',
                        ],
                    ]],
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/submissions/169/publications/501' => Http::response([
                'id' => 501,
                'issueId' => 32,
                'title' => ['es_ES' => 'Articulo de prueba'],
                'abstract' => ['es_ES' => 'Resumen corto'],
                'authors' => [[
                    'givenName' => 'Ana',
                    'familyName' => 'Perez',
                ]],
                'authorsString' => 'Ana Perez',
                'galleys' => [[
                    'label' => 'PDF',
                    'urlPublished' => 'https://investigacion.example/ojs/article/download/169/demo.pdf',
                    'submissionFile' => [
                        'name' => 'demo.pdf',
                        'mimetype' => 'application/pdf',
                    ],
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10/submissions*' => Http::response([
                'items' => [[
                    'id' => 169,
                    'issueId' => 32,
                    'datePublished' => '2024-01-20',
                    'publications' => [[
                        'id' => 501,
                        'issueId' => 32,
                        'title' => ['es_ES' => 'Articulo de prueba'],
                        'abstract' => ['es_ES' => 'Resumen corto'],
                        'authors' => [[
                            'givenName' => 'Ana',
                            'familyName' => 'Perez',
                        ]],
                        'authorsString' => 'Ana Perez',
                        'doi' => 'https://doi.org/10.1234/demo',
                        'galleys' => [[
                            'label' => 'PDF',
                            'urlPublished' => 'https://investigacion.example/ojs/article/download/169/demo.pdf',
                            'submissionFile' => [
                                'name' => 'demo.pdf',
                                'mimetype' => 'application/pdf',
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ]);

        $journals = $this->getJson('/api/v1/journals?source=investigacion');
        $journals
            ->assertOk()
            ->assertJsonPath('data.0.id', 'investigacion:10')
            ->assertJsonPath('data.0.name', 'Revista Cientifica UNITEPC');

        $issues = $this->getJson('/api/v1/journals/investigacion:10/issues');
        $issues
            ->assertOk()
            ->assertJsonPath('data.0.id', 'investigacion:10:32')
            ->assertJsonPath('data.0.title', 'Vol. 8 No. 1 (2024)');

        $articles = $this->getJson('/api/v1/issues/investigacion:10:32/articles');
        $articles
            ->assertOk()
            ->assertJsonPath('data.0.id', 'investigacion:10:169')
            ->assertJsonPath('data.0.title', 'Articulo de prueba')
            ->assertJsonPath('data.0.authorsString', 'Ana Perez')
            ->assertJsonPath('data.0.pdf.filename', 'demo.pdf');

        $article = $this->getJson('/api/v1/articles/investigacion:10:169');
        $article
            ->assertOk()
            ->assertJsonPath('data.id', 'investigacion:10:169')
            ->assertJsonPath('data.pdf.url', 'https://investigacion.example/ojs/article/download/169/demo.pdf');
    }

    public function test_it_returns_controlled_error_when_source_is_unavailable(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('Timeout');
        });

        $response = $this->getJson('/api/v1/journals?source=investigacion');

        $response
            ->assertStatus(502)
            ->assertJsonPath('message', 'The upstream OJS source is unavailable right now.');
    }

    public function test_it_streams_pdf_and_forwards_range_headers(): void
    {
        Http::fake([
            'https://investigacion.example/ojs/index.php/_/api/v1/contexts' => Http::response([
                'items' => [[
                    'id' => 10,
                    'name' => ['es_ES' => 'Revista Cientifica UNITEPC'],
                    'path' => 'revista-unitepc',
                    '_href' => 'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10',
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/contexts/10/submissions/169' => Http::response([
                'id' => 169,
                'currentPublicationId' => 501,
                'publications' => [[
                    'id' => 501,
                    'title' => ['es_ES' => 'Articulo de prueba'],
                    '_href' => 'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/submissions/169/publications/501',
                    'galleys' => [[
                        'label' => 'PDF',
                        'urlPublished' => 'https://investigacion.example/ojs/article/download/169/demo.pdf',
                        'submissionFile' => [
                            'name' => 'demo.pdf',
                            'mimetype' => 'application/pdf',
                        ],
                    ]],
                ]],
            ]),
            'https://investigacion.example/ojs/index.php/revista-unitepc/api/v1/submissions/169/publications/501' => Http::response([
                'id' => 501,
                'title' => ['es_ES' => 'Articulo de prueba'],
                'galleys' => [[
                    'label' => 'PDF',
                    'urlPublished' => 'https://investigacion.example/ojs/article/download/169/demo.pdf',
                    'submissionFile' => [
                        'name' => 'demo.pdf',
                        'mimetype' => 'application/pdf',
                    ],
                ]],
            ]),
            'https://investigacion.example/ojs/article/download/169/demo.pdf' => Http::response(
                body: 'partial pdf body',
                status: 206,
                headers: [
                    'Content-Type' => 'application/pdf',
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => 'bytes 0-15/100',
                    'Content-Length' => '16',
                ],
            ),
        ]);

        $response = $this->call('GET', '/api/v1/articles/investigacion:10:169/pdf?disposition=attachment', server: [
            'HTTP_RANGE' => 'bytes=0-15',
        ]);

        $response
            ->assertStatus(206)
            ->assertHeader('Content-Disposition', 'attachment; filename="demo.pdf"')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-15/100');

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://investigacion.example/ojs/article/download/169/demo.pdf'
                && $request->hasHeader('Range', 'bytes=0-15');
        });
    }
}

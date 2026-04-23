<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'compound_id',
        'journal_id',
        'issue_id',
        'journal_compound_id',
        'issue_compound_id',
        'source_slug',
        'remote_id',
        'title',
        'subtitle',
        'authors',
        'authors_string',
        'abstract',
        'keywords',
        'doi',
        'pages',
        'published_at',
        'url',
        'pdf',
        'citations',
        'license_url',
        'references',
        'section',
        'search_blob',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'keywords' => 'array',
            'pdf' => 'array',
            'citations' => 'array',
            'references' => 'array',
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CatalogJournal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(CatalogJournal::class, 'journal_id');
    }

    /**
     * @return BelongsTo<CatalogIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(CatalogIssue::class, 'issue_id');
    }
}

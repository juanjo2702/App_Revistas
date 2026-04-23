<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'compound_id',
        'journal_id',
        'journal_compound_id',
        'source_slug',
        'remote_id',
        'title',
        'volume',
        'number',
        'year',
        'published_at',
        'description',
        'cover_url',
        'url',
        'pdf',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'published_at' => 'datetime',
            'pdf' => 'array',
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
     * @return HasMany<CatalogArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(CatalogArticle::class, 'issue_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'compound_id',
        'source_slug',
        'remote_id',
        'name',
        'description',
        'issn',
        'url',
        'thumbnail_url',
        'api_href',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CatalogIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(CatalogIssue::class, 'journal_id');
    }

    /**
     * @return HasMany<CatalogArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(CatalogArticle::class, 'journal_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'edition_id',
        'title',
        'body',
        'body_excerpt',
        'section',
        'page_number',
        'publication_date',
        'newspaper_name',
        'content_hash',
        'word_count',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'page_number' => 'integer',
            'word_count' => 'integer',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag')
            ->withPivot('relevance_score');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw(
            "search_vector @@ plainto_tsquery('spanish', ?)",
            [$term]
        )->orderByRaw(
            "ts_rank(search_vector, plainto_tsquery('spanish', ?)) DESC",
            [$term]
        );
    }

    public function scopeBySection($query, string $section)
    {
        return $query->where('section', $section);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('publication_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('publication_date', '<=', $to);
        }

        return $query;
    }

    public function scopeByTag($query, string $tagName)
    {
        return $query->whereHas('tags', fn ($q) => $q->where('name', $tagName));
    }
}

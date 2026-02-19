<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Edition;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ArticleService
{
    private const PER_PAGE = 20;

    /**
     * Search articles with optional query and filters.
     *
     * @param  array{section?: string, date_from?: string, date_to?: string, tag?: string}  $filters
     */
    public function search(string $query = '', array $filters = []): LengthAwarePaginator
    {
        try {
            return $this->searchViaDb($query, $filters);
        } catch (\Exception $e) {
            return $this->searchViaRest($query, $filters);
        }
    }

    private function searchViaDb(string $query, array $filters): LengthAwarePaginator
    {
        $builder = Article::with('tags')->orderBy('publication_date', 'desc');

        // Full-text search using PostgreSQL tsvector
        if (! empty($query)) {
            $builder->where(function ($q) use ($query) {
                // Primary: use search_vector GIN index
                $q->whereRaw(
                    "search_vector @@ plainto_tsquery('spanish', ?)",
                    [$query]
                )
                // Fallback: ILIKE on title (for partial matches or when search_vector is not yet updated)
                ->orWhere('title', 'ILIKE', '%' . $query . '%');
            });

            // Apply relevance ordering when searching
            $builder->orderByRaw(
                "ts_rank(search_vector, plainto_tsquery('spanish', ?)) DESC",
                [$query]
            );
        }

        // Section filter
        if (! empty($filters['section'])) {
            $builder->where('section', $filters['section']);
        }

        // Date range filter
        if (! empty($filters['date_from'])) {
            $builder->whereDate('publication_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $builder->whereDate('publication_date', '<=', $filters['date_to']);
        }

        // Tag filter
        if (! empty($filters['tag'])) {
            $builder->whereHas('tags', fn ($q) => $q->where('name', $filters['tag']));
        }

        return $builder->paginate(self::PER_PAGE);
    }

    private function searchViaRest(string $query, array $filters): LengthAwarePaginator
    {
        $rest = new SupabaseRestService();
        if (! $rest->available()) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, self::PER_PAGE);
        }

        $page = (int) request()->get('page', 1);

        return $rest->searchArticles($query, $filters, $page, self::PER_PAGE);
    }

    /**
     * Find articles related to a given article based on shared tags.
     */
    public function findRelated(Article $article, int $limit = 5): Collection
    {
        $tagIds = $article->tags->pluck('id');

        if ($tagIds->isEmpty()) {
            return new Collection();
        }

        return Article::with('tags')
            ->where('id', '!=', $article->id)
            ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
            ->select('articles.*')
            ->selectRaw(
                '(SELECT COUNT(*) FROM article_tag at2 WHERE at2.article_id = articles.id AND at2.tag_id IN (' . implode(',', $tagIds->toArray()) . ')) as shared_tags'
            )
            ->orderByDesc('shared_tags')
            ->orderByDesc('publication_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get aggregate statistics for the dashboard.
     *
     * @return array{total_articles: int, total_editions: int, by_section: array, by_year: array}
     */
    public function getStats(): array
    {
        $bySection = Article::select('section', DB::raw('COUNT(*) as count'))
            ->whereNotNull('section')
            ->groupBy('section')
            ->orderByDesc('count')
            ->pluck('count', 'section')
            ->toArray();

        $byYear = Article::select(DB::raw('EXTRACT(YEAR FROM publication_date) as year'), DB::raw('COUNT(*) as count'))
            ->groupBy('year')
            ->orderByDesc('year')
            ->pluck('count', 'year')
            ->toArray();

        return [
            'total_articles' => Article::count(),
            'total_editions' => Edition::count(),
            'by_section' => $bySection,
            'by_year' => $byYear,
        ];
    }

    /**
     * Get distinct sections that have at least one article.
     *
     * @return string[]
     */
    public function getSections(): array
    {
        try {
            return Article::select('section')
                ->whereNotNull('section')
                ->distinct()
                ->orderBy('section')
                ->pluck('section')
                ->toArray();
        } catch (\Exception $e) {
            $rest = new SupabaseRestService();
            return $rest->available() ? $rest->getSections() : [];
        }
    }
}

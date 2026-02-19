<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Fallback service that reads from Supabase PostgREST API (port 443/HTTPS)
 * when the native PostgreSQL pooler is unavailable.
 */
class SupabaseRestService
{
    private string $baseUrl;
    private string $key;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('SUPABASE_URL', ''), '/') . '/rest/v1';
        $this->key     = (string) env('SUPABASE_SERVICE_KEY', '');
    }

    public function available(): bool
    {
        return $this->baseUrl !== '/rest/v1' && $this->key !== '';
    }

    // ── Public articles list ────────────────────────────────────────────────

    public function searchArticles(
        string $query,
        array  $filters,
        int    $page,
        int    $perPage
    ): LengthAwarePaginator {
        $select = 'id,title,body_excerpt,section,page_number,publication_date,newspaper_name,edition_id,article_tag(tags(id,name,display_name))';
        $params = ['select' => $select, 'order' => 'publication_date.desc'];

        if (! empty($filters['section'])) {
            $params['section'] = 'eq.' . $filters['section'];
        }
        if (! empty($filters['date_from'])) {
            $params['publication_date'] = 'gte.' . $filters['date_from'];
        }

        // Simple title ILIKE search (no full-text via REST)
        if (! empty($query)) {
            $params['title'] = 'ilike.*' . $query . '*';
        }

        $total = $this->fetchCount($params);

        $params['limit']  = $perPage;
        $params['offset'] = ($page - 1) * $perPage;

        $rows  = $this->get('articles', $params);
        $items = $this->rowsToArticles($rows);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getSections(): array
    {
        $rows = $this->get('articles', [
            'select'   => 'section',
            'order'    => 'section.asc',
            'section'  => 'not.is.null',
        ]);

        $sections = array_unique(array_filter(array_column($rows, 'section')));
        sort($sections);

        return array_values($sections);
    }

    // ── Dashboard counts ────────────────────────────────────────────────────

    public function countArticles(): int
    {
        return $this->fetchCount(['select' => 'id']);
    }

    public function countEditions(): int
    {
        return $this->fetchCountTable('editions', ['select' => 'id']);
    }

    public function countEditionsByStatus(string $status): int
    {
        return $this->fetchCountTable('editions', ['select' => 'id', 'status' => 'eq.' . $status]);
    }

    public function countEditionsByStatuses(array $statuses): int
    {
        $total = 0;
        foreach ($statuses as $s) {
            $total += $this->countEditionsByStatus($s);
        }
        return $total;
    }

    public function recentEditions(int $limit = 10): array
    {
        return $this->get('editions', [
            'select' => 'id,edition_date,newspaper_name,status,created_at,file_path,page_count',
            'order'  => 'created_at.desc',
            'limit'  => $limit,
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function fetchCount(array $params): int
    {
        return $this->fetchCountTable('articles', $params);
    }

    private function fetchCountTable(string $table, array $params): int
    {
        // Use HEAD request with Prefer: count=exact
        $url = $this->baseUrl . '/' . $table . '?' . http_build_query($params);

        $context = stream_context_create(['http' => [
            'method'        => 'HEAD',
            'header'        => $this->headers(['Prefer: count=exact']),
            'ignore_errors' => true,
        ]]);

        @file_get_contents($url, false, $context);

        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $h, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $h, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    private function get(string $table, array $params): array
    {
        $url = $this->baseUrl . '/' . $table . '?' . http_build_query($params);

        $context = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => $this->headers(),
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) ? (json_decode($body, true) ?? []) : [];
    }

    private function headers(array $extra = []): string
    {
        $base = [
            "apikey: {$this->key}",
            "Authorization: Bearer {$this->key}",
            'Accept: application/json',
        ];

        return implode("\r\n", array_merge($base, $extra));
    }

    /** @param array<int,array> $rows */
    private function rowsToArticles(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): Article {
            $article = new Article();
            $article->setRawAttributes([
                'id'               => $row['id'] ?? null,
                'title'            => $row['title'] ?? '',
                'body_excerpt'     => $row['body_excerpt'] ?? '',
                'section'          => $row['section'] ?? '',
                'page_number'      => $row['page_number'] ?? 0,
                'publication_date' => $row['publication_date'] ?? null,
                'newspaper_name'   => $row['newspaper_name'] ?? '',
                'edition_id'       => $row['edition_id'] ?? null,
            ]);
            $article->exists = true;

            $tags = collect($row['article_tag'] ?? [])->map(function (array $at): Tag {
                $t = new Tag();
                $t->setRawAttributes($at['tags'] ?? []);
                $t->exists = true;
                return $t;
            });

            $article->setRelation('tags', $tags);

            return $article;
        });
    }
}

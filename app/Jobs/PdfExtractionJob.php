<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Edition;
use App\Services\ArticleSegmentationService;
use App\Services\ClaudeExtractionService;
use App\Services\PdfParserService;
use App\Services\SupabaseRestService;
use App\Services\TagGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public Edition $edition) {}

    public function handle(
        PdfParserService $parser,
        ClaudeExtractionService $claude,
        ArticleSegmentationService $segmenter,
        TagGeneratorService $tagger,
        SupabaseRestService $rest
    ): void {
        $editionId = $this->edition->id;

        // ── 1. Create extraction job record via REST ────────────────────────
        // Note: extraction_jobs table has no updated_at column ($timestamps = false)
        $now    = now()->toIso8601String();
        $jobRow = $this->safeInsert($rest, 'extraction_jobs', [
            'edition_id'         => $editionId,
            'status'             => 'running',
            'articles_extracted' => 0,
            'errors'             => json_encode([]),
            'started_at'         => $now,
            'created_at'         => $now,
        ]);
        $jobId = $jobRow['id'] ?? null;

        // ── 2. Mark edition as processing ──────────────────────────────────
        $this->safeUpdate($rest, 'editions', $editionId, [
            'status'     => 'processing',
            'updated_at' => $now,
        ]);

        try {
            // ── 3. Resolve file path ────────────────────────────────────────
            $filePath = $this->resolveFilePath();

            Log::info('PdfExtractionJob: starting extraction', [
                'edition_id' => $editionId,
                'file'       => $filePath,
            ]);

            // ── 4. Extract text from PDF ────────────────────────────────────
            $pages     = $parser->extractPages($filePath);
            $pageCount = count($pages);

            if ($jobId) {
                $this->safeUpdate($rest, 'extraction_jobs', $jobId, [
                    'page_total'   => $pageCount,
                    'page_current' => 0,
                ]);
            }

            // ── 5. Extract articles (Claude AI first, heuristic fallback) ───
            $publicationDate = $this->edition->publication_date
                ? $this->edition->publication_date->toDateString()
                : now()->toDateString();
            $newspaperName = $this->edition->newspaper_name ?? 'El Heraldo';

            $articleData = [];

            if ($claude->available()) {
                Log::info('PdfExtractionJob: using Claude AI', ['edition_id' => $editionId]);
                $articleData = $claude->extractArticles($pages, $publicationDate, $newspaperName);
            }

            // Fallback: heuristic segmentation if Claude returned nothing
            if (empty($articleData)) {
                Log::info('PdfExtractionJob: using heuristic segmentation (fallback)', ['edition_id' => $editionId]);
                $segmented = $segmenter->segment($pages);
                foreach ($segmented as $seg) {
                    $generated   = $tagger->generate($seg['body']);
                    $articleData[] = array_merge($seg, ['tags' => array_column($generated, 'name')]);
                }
            }

            // ── 6. Save articles to Supabase via REST ───────────────────────
            $articlesExtracted = 0;
            $errors            = [];

            foreach ($articleData as $data) {
                try {
                    $title       = (string) ($data['title'] ?? '');
                    $body        = (string) ($data['body'] ?? '');
                    $contentHash = hash('sha256', $title . $body);

                    // Skip duplicates
                    $existing = $rest->findOne('articles', ['content_hash' => 'eq.' . $contentHash]);
                    if ($existing !== null) {
                        continue;
                    }

                    $nowTs   = now()->toIso8601String();
                    $article = $rest->insert('articles', [
                        'edition_id'       => $editionId,
                        'title'            => $title,
                        'body'             => $body,
                        'body_excerpt'     => mb_substr($body, 0, 300),
                        'section'          => $data['section'] ?? null,
                        'page_number'      => (int) ($data['page_number'] ?? 1),
                        'publication_date' => $publicationDate,
                        'newspaper_name'   => $newspaperName,
                        'content_hash'     => $contentHash,
                        'word_count'       => (int) ($data['word_count'] ?? str_word_count(strip_tags($body))),
                        'created_at'       => $nowTs,
                        'updated_at'       => $nowTs,
                    ]);

                    $articleId = $article['id'] ?? null;

                    if ($articleId) {
                        $tagNames = (array) ($data['tags'] ?? []);

                        // Generate tags with TF-IDF if none provided
                        if (empty($tagNames)) {
                            $generated = $tagger->generate($body);
                            $tagNames  = array_column($generated, 'name');
                        }

                        foreach ($tagNames as $tagName) {
                            $tagName = strtolower(trim((string) $tagName));
                            if (mb_strlen($tagName) < 3) {
                                continue;
                            }

                            // Note: tags table has no updated_at column
                            $tag = $rest->firstOrCreate('tags', ['name' => $tagName], [
                                'display_name'  => $tagName,
                                'article_count' => 0,
                                'created_at'    => $nowTs,
                            ]);

                            $tagId = $tag['id'] ?? null;
                            if ($tagId) {
                                $rest->insertPivot('article_tag', [
                                    'article_id'      => $articleId,
                                    'tag_id'          => $tagId,
                                    'relevance_score' => 0.5,
                                ]);
                                $rest->increment('tags', ['id' => 'eq.' . $tagId], 'article_count');
                            }
                        }
                    }

                    $articlesExtracted++;

                    if ($jobId) {
                        $this->safeUpdate($rest, 'extraction_jobs', $jobId, [
                            'articles_extracted' => $articlesExtracted,
                            'page_current'       => (int) ($data['page_number'] ?? 1),
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'page'  => $data['page_number'] ?? null,
                        'title' => mb_substr($data['title'] ?? '', 0, 100),
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('PdfExtractionJob: error saving article', [
                        'edition_id' => $editionId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // ── 7. Mark edition as completed ───────────────────────────────
            $processingLog = json_encode([
                'pages_extracted' => $pageCount,
                'articles_found'  => count($articleData),
                'articles_saved'  => $articlesExtracted,
                'errors'          => $errors,
                'extraction_mode' => $claude->available() ? 'claude-ai' : 'heuristic',
            ]);

            $this->safeUpdate($rest, 'editions', $editionId, [
                'status'         => 'completed',
                'total_articles' => $articlesExtracted,
                'total_pages'    => $pageCount,
                'processed_at'   => now()->toIso8601String(),
                'processing_log' => $processingLog,
                'updated_at'     => now()->toIso8601String(),
            ]);

            if ($jobId) {
                $this->safeUpdate($rest, 'extraction_jobs', $jobId, [
                    'status'             => 'completed',
                    'articles_extracted' => $articlesExtracted,
                    'page_current'       => $pageCount,
                    'page_total'         => $pageCount,
                    'errors'             => json_encode($errors),
                    'finished_at'        => now()->toIso8601String(),
                ]);
            }

            Log::info('PdfExtractionJob: completed', [
                'edition_id' => $editionId,
                'articles'   => $articlesExtracted,
                'mode'       => $claude->available() ? 'claude-ai' : 'heuristic',
            ]);
        } catch (\Exception $e) {
            Log::error('PdfExtractionJob: failed', [
                'edition_id' => $editionId,
                'error'      => $e->getMessage(),
            ]);

            $this->safeUpdate($rest, 'editions', $editionId, [
                'status'         => 'error',
                'processing_log' => json_encode(['error' => $e->getMessage()]),
                'updated_at'     => now()->toIso8601String(),
            ]);

            if ($jobId) {
                $this->safeUpdate($rest, 'extraction_jobs', $jobId, [
                    'status'      => 'failed',
                    'errors'      => json_encode([['error' => $e->getMessage()]]),
                    'finished_at' => now()->toIso8601String(),
                ]);
            }

            throw $e;
        }
    }

    private function resolveFilePath(): string
    {
        $storedPath = $this->edition->file_path;

        if (file_exists($storedPath)) {
            return $storedPath;
        }

        // Vercel: storage redirected to /tmp
        $tmpPath = '/tmp/storage/app/' . $storedPath;
        if (file_exists($tmpPath)) {
            return $tmpPath;
        }

        $localPath = storage_path('app/' . $storedPath);
        if (file_exists($localPath)) {
            return $localPath;
        }

        if (Storage::exists($storedPath)) {
            return Storage::path($storedPath);
        }

        throw new \RuntimeException("Cannot resolve file path: {$storedPath}");
    }

    private function safeInsert(SupabaseRestService $rest, string $table, array $data): array
    {
        try {
            return $rest->insert($table, $data);
        } catch (\Exception $e) {
            Log::warning("PdfExtractionJob: REST insert failed on {$table}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function safeUpdate(SupabaseRestService $rest, string $table, int $id, array $data): void
    {
        try {
            $rest->update($table, ['id' => 'eq.' . $id], $data);
        } catch (\Exception $e) {
            Log::warning("PdfExtractionJob: REST update failed on {$table}", [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

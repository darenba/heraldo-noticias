<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Article;
use App\Models\Edition;
use App\Models\ExtractionJob;
use App\Models\Tag;
use App\Services\ArticleSegmentationService;
use App\Services\PdfParserService;
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

    public int $tries = 2;

    public function __construct(public Edition $edition) {}

    public function handle(
        PdfParserService $parser,
        ArticleSegmentationService $segmenter,
        TagGeneratorService $tagger
    ): void {
        // Create extraction job record
        $extractionJob = ExtractionJob::create([
            'edition_id' => $this->edition->id,
            'status' => 'running',
            'started_at' => now(),
            'errors' => [],
        ]);

        // Update edition status
        $this->edition->update(['status' => 'processing']);

        try {
            // Resolve file path
            $filePath = $this->resolveFilePath();

            // Extract pages from PDF
            Log::info("PdfExtractionJob: starting extraction", ['edition_id' => $this->edition->id, 'file' => $filePath]);

            $pages = $parser->extractPages($filePath);

            $extractionJob->update([
                'page_total' => count($pages),
                'page_current' => 0,
            ]);

            // Segment pages into articles
            $articleData = $segmenter->segment($pages);

            $articlesExtracted = 0;
            $errors = [];

            foreach ($articleData as $data) {
                try {
                    // Compute content hash for deduplication
                    $contentHash = hash('sha256', $data['title'] . $data['body']);

                    // Skip duplicates
                    if (Article::where('content_hash', $contentHash)->exists()) {
                        continue;
                    }

                    // Create the article
                    $article = Article::create([
                        'edition_id' => $this->edition->id,
                        'title' => $data['title'],
                        'body' => $data['body'],
                        'body_excerpt' => $data['body_excerpt'],
                        'section' => $data['section'],
                        'page_number' => $data['page_number'],
                        'publication_date' => $this->edition->publication_date,
                        'newspaper_name' => $this->edition->newspaper_name,
                        'content_hash' => $contentHash,
                        'word_count' => $data['word_count'],
                    ]);

                    // Generate and attach tags
                    $tags = $tagger->generate($data['body']);
                    foreach ($tags as $tagData) {
                        $tag = Tag::firstOrCreate(
                            ['name' => $tagData['name']],
                            ['display_name' => $tagData['display_name'], 'article_count' => 0]
                        );
                        $article->tags()->attach($tag->id, ['relevance_score' => $tagData['score']]);
                        // Increment count (trigger handles this in DB, but keep model in sync)
                        $tag->increment('article_count');
                    }

                    $articlesExtracted++;

                    // Update job progress
                    $extractionJob->update([
                        'articles_extracted' => $articlesExtracted,
                        'page_current' => $data['page_number'],
                    ]);
                } catch (\Exception $e) {
                    $errors[] = [
                        'page' => $data['page_number'],
                        'title' => $data['title'],
                        'error' => $e->getMessage(),
                    ];
                    Log::warning("PdfExtractionJob: error creating article", [
                        'edition_id' => $this->edition->id,
                        'title' => $data['title'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update edition as completed
            $this->edition->update([
                'status' => 'completed',
                'total_articles' => $articlesExtracted,
                'total_pages' => count($pages),
                'processed_at' => now(),
                'processing_log' => [
                    'pages_extracted' => count($pages),
                    'articles_found' => count($articleData),
                    'articles_saved' => $articlesExtracted,
                    'errors' => $errors,
                ],
            ]);

            $extractionJob->update([
                'status' => 'completed',
                'articles_extracted' => $articlesExtracted,
                'page_current' => count($pages),
                'page_total' => count($pages),
                'errors' => $errors,
                'finished_at' => now(),
            ]);

            Log::info("PdfExtractionJob: completed", [
                'edition_id' => $this->edition->id,
                'articles' => $articlesExtracted,
            ]);
        } catch (\Exception $e) {
            Log::error("PdfExtractionJob: failed", [
                'edition_id' => $this->edition->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->edition->update([
                'status' => 'error',
                'processing_log' => ['error' => $e->getMessage()],
            ]);

            $extractionJob->update([
                'status' => 'failed',
                'errors' => [['error' => $e->getMessage()]],
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function resolveFilePath(): string
    {
        $storedPath = $this->edition->file_path;

        // Check if it's an absolute path (for local imports)
        if (file_exists($storedPath)) {
            return $storedPath;
        }

        // Try storage path
        $localPath = storage_path('app/' . $storedPath);
        if (file_exists($localPath)) {
            return $localPath;
        }

        // Try Storage facade
        if (Storage::exists($storedPath)) {
            return Storage::path($storedPath);
        }

        throw new \RuntimeException("Cannot resolve file path: {$storedPath}");
    }
}

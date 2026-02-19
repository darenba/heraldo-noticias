<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\PdfExtractionJob;
use App\Models\Edition;
use App\Models\ExtractionJob;
use App\Services\ArticleSegmentationService;
use App\Services\PdfParserService;
use App\Services\TagGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PdfExtractionJobTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Queue dispatch
    // -------------------------------------------------------------------------

    public function test_job_can_be_pushed_to_queue(): void
    {
        Queue::fake();

        $edition = Edition::factory()->create(['status' => 'pending']);

        PdfExtractionJob::dispatch($edition);

        Queue::assertPushed(PdfExtractionJob::class, function ($job) use ($edition) {
            return true; // Just verify it was pushed
        });
    }

    public function test_job_is_on_default_queue(): void
    {
        Queue::fake();

        $edition = Edition::factory()->create(['status' => 'pending']);

        PdfExtractionJob::dispatch($edition);

        Queue::assertPushedOn('default', PdfExtractionJob::class);
    }

    // -------------------------------------------------------------------------
    // Job construction & retries
    // -------------------------------------------------------------------------

    public function test_job_timeout_is_300_seconds(): void
    {
        $edition = Edition::factory()->make();
        $job = new PdfExtractionJob($edition);

        $this->assertSame(300, $job->timeout);
    }

    public function test_job_tries_is_2(): void
    {
        $edition = Edition::factory()->make();
        $job = new PdfExtractionJob($edition);

        $this->assertSame(2, $job->tries);
    }

    // -------------------------------------------------------------------------
    // handle() — success path (mocked services)
    // -------------------------------------------------------------------------

    public function test_handle_marks_edition_completed_on_success(): void
    {
        $edition = Edition::factory()->create([
            'status'     => 'pending',
            'file_path'  => '/tmp/fake.pdf',
        ]);

        // Mock services
        $parser = $this->createMock(PdfParserService::class);
        $parser->method('extractPages')->willReturn([
            ['page' => 1, 'text' => 'TITULAR LARGO SUFICIENTE PARA SER DETECTADO'],
        ]);
        $parser->method('getTotalPages')->willReturn(1);

        $segmenter = $this->createMock(ArticleSegmentationService::class);
        $segmenter->method('segment')->willReturn([
            [
                'title'        => 'TITULAR LARGO SUFICIENTE PARA SER DETECTADO',
                'body'         => 'Cuerpo de artículo de prueba con suficiente contenido para el test.',
                'body_excerpt' => 'Cuerpo de artículo de prueba.',
                'section'      => 'General',
                'page_number'  => 1,
                'word_count'   => 12,
            ],
        ]);

        $tagger = $this->createMock(TagGeneratorService::class);
        $tagger->method('generate')->willReturn([]);

        $job = new PdfExtractionJob($edition);
        $job->handle($parser, $segmenter, $tagger);

        $edition->refresh();
        $this->assertSame('completed', $edition->status);
        $this->assertNotNull($edition->processed_at);
    }

    public function test_handle_sets_status_error_on_exception(): void
    {
        $edition = Edition::factory()->create([
            'status'    => 'pending',
            'file_path' => '/nonexistent/path.pdf',
        ]);

        $parser = $this->createMock(PdfParserService::class);
        $parser->method('extractPages')->willThrowException(new \RuntimeException('File not found'));
        $parser->method('getTotalPages')->willReturn(0);

        $segmenter = $this->createMock(ArticleSegmentationService::class);
        $tagger    = $this->createMock(TagGeneratorService::class);

        $job = new PdfExtractionJob($edition);

        try {
            $job->handle($parser, $segmenter, $tagger);
        } catch (\Throwable) {
            // Job re-throws after marking error
        }

        $edition->refresh();
        $this->assertSame('error', $edition->status);
    }

    public function test_handle_creates_extraction_job_record(): void
    {
        $edition = Edition::factory()->create([
            'status'    => 'pending',
            'file_path' => '/tmp/fake.pdf',
        ]);

        $parser = $this->createMock(PdfParserService::class);
        $parser->method('extractPages')->willReturn([]);
        $parser->method('getTotalPages')->willReturn(0);

        $segmenter = $this->createMock(ArticleSegmentationService::class);
        $segmenter->method('segment')->willReturn([]);

        $tagger = $this->createMock(TagGeneratorService::class);

        $job = new PdfExtractionJob($edition);
        $job->handle($parser, $segmenter, $tagger);

        $this->assertDatabaseHas('extraction_jobs', ['edition_id' => $edition->id]);
    }

    public function test_handle_skips_duplicate_articles(): void
    {
        $edition = Edition::factory()->create([
            'status'    => 'pending',
            'file_path' => '/tmp/fake.pdf',
        ]);

        $article = [
            'title'        => 'TITULAR DUPLICADO PARA VERIFICAR DEDUP',
            'body'         => 'Cuerpo idéntico que debe ser detectado como duplicado en el sistema.',
            'body_excerpt' => 'Cuerpo idéntico.',
            'section'      => 'General',
            'page_number'  => 1,
            'word_count'   => 12,
        ];

        $parser = $this->createMock(PdfParserService::class);
        $parser->method('extractPages')->willReturn([['page' => 1, 'text' => '']]);
        $parser->method('getTotalPages')->willReturn(1);

        $segmenter = $this->createMock(ArticleSegmentationService::class);
        // Return same article twice (simulates duplicate detection from two pages)
        $segmenter->method('segment')->willReturn([$article, $article]);

        $tagger = $this->createMock(TagGeneratorService::class);
        $tagger->method('generate')->willReturn([]);

        $job = new PdfExtractionJob($edition);
        $job->handle($parser, $segmenter, $tagger);

        // Only 1 unique article should be stored (dedup by content_hash)
        $this->assertDatabaseCount('articles', 1);
    }
}

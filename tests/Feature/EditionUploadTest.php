<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditionUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // -------------------------------------------------------------------------
    // Create form
    // -------------------------------------------------------------------------

    public function test_create_page_returns_200_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.editions.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.editions.create');
    }

    public function test_create_page_redirects_guest_to_login(): void
    {
        $response = $this->get(route('admin.editions.create'));

        $response->assertRedirect(route('admin.login'));
    }

    // -------------------------------------------------------------------------
    // Store — valid upload
    // -------------------------------------------------------------------------

    public function test_valid_pdf_upload_creates_edition_record(): void
    {
        $file = UploadedFile::fake()->create('EH2024-01-15-edicion.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file'             => $file,
            'publication_date' => '2024-01-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('editions', [
            'filename'         => 'EH2024-01-15-edicion.pdf',
            'publication_date' => '2024-01-15',
            'status'           => 'pending',
        ]);
    }

    public function test_valid_pdf_upload_dispatches_extraction_job(): void
    {
        $file = UploadedFile::fake()->create('EH2024-01-15.pdf', 500, 'application/pdf');

        $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file'             => $file,
            'publication_date' => '2024-01-15',
        ]);

        Queue::assertPushed(\App\Jobs\PdfExtractionJob::class);
    }

    public function test_upload_stores_file_on_disk(): void
    {
        $file = UploadedFile::fake()->create('EH2024-01-15.pdf', 500, 'application/pdf');

        $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file'             => $file,
            'publication_date' => '2024-01-15',
        ]);

        $edition = Edition::first();
        $this->assertNotNull($edition);
        Storage::disk('local')->assertExists($edition->file_path);
    }

    public function test_upload_extracts_date_from_filename_when_not_provided(): void
    {
        $file = UploadedFile::fake()->create('EH2024-03-22-domingo.pdf', 200, 'application/pdf');

        $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file' => $file,
        ]);

        $this->assertDatabaseHas('editions', ['publication_date' => '2024-03-22']);
    }

    // -------------------------------------------------------------------------
    // Store — duplicate detection
    // -------------------------------------------------------------------------

    public function test_duplicate_pdf_is_rejected(): void
    {
        $content = 'fake-pdf-content-for-hash-testing';
        $hash    = hash('sha256', $content);

        Edition::factory()->create(['file_hash' => $hash]);

        // Create fake file with same content hash
        $file = UploadedFile::fake()->createWithContent('EH2024-01-20.pdf', $content);

        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('editions', 1);
    }

    // -------------------------------------------------------------------------
    // Store — validation failures
    // -------------------------------------------------------------------------

    public function test_upload_fails_without_file(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'publication_date' => '2024-01-15',
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_fails_with_non_pdf_file(): void
    {
        $file = UploadedFile::fake()->create('document.docx', 100, 'application/msword');

        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_fails_when_file_exceeds_50mb(): void
    {
        // 51 MB
        $file = UploadedFile::fake()->create('huge.pdf', 51 * 1024, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_upload_fails_with_invalid_date_format(): void
    {
        $file = UploadedFile::fake()->create('EH2024-01-15.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('admin.editions.store'), [
            'file'             => $file,
            'publication_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('publication_date');
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_200_for_existing_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->admin)->get(route('admin.editions.show', $edition));

        $response->assertStatus(200);
        $response->assertViewIs('admin.editions.show');
    }

    public function test_show_returns_404_for_unknown_edition(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.editions.show', 9999));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_completed_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->admin)->delete(route('admin.editions.destroy', $edition));

        $response->assertRedirect(route('admin.editions.index'));
        $this->assertDatabaseMissing('editions', ['id' => $edition->id]);
    }

    public function test_cannot_delete_processing_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'processing']);

        $response = $this->actingAs($this->admin)->delete(route('admin.editions.destroy', $edition));

        // Should be forbidden or redirected with error
        $response->assertStatus(403)->orRedirectWith(fn ($r) => $r->assertSessionHasErrors());
        $this->assertDatabaseHas('editions', ['id' => $edition->id]);
    }

    // -------------------------------------------------------------------------
    // Job status API
    // -------------------------------------------------------------------------

    public function test_status_endpoint_returns_json(): void
    {
        $edition = Edition::factory()->create(['status' => 'processing']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.editions.status', $edition));

        $response->assertStatus(200);
        $response->assertJsonStructure(['status']);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PdfExtractionJob;
use App\Models\Edition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EditionService
{
    /**
     * Create an Edition from an uploaded file and dispatch the extraction job.
     * Falls back to REST API if the native DB connection fails.
     */
    public function createFromUpload(UploadedFile $file, ?string $publicationDate): Edition
    {
        $fileHash = hash_file('sha256', $file->getRealPath());

        $existing = $this->findByHash($fileHash);
        if ($existing !== null) {
            return $existing;
        }

        if (empty($publicationDate)) {
            $publicationDate = $this->extractDateFromFilename($file->getClientOriginalName());
        }

        if (empty($publicationDate)) {
            $publicationDate = now()->toDateString();
        }

        // Store file (goes to /tmp/storage/app/pdfs/ on Vercel)
        $storedPath = $file->store('pdfs', 'local');

        $editionData = [
            'filename'         => $file->getClientOriginalName(),
            'file_path'        => $storedPath,
            'file_hash'        => $fileHash,
            'publication_date' => $publicationDate,
            'newspaper_name'   => 'El Heraldo',
            'status'           => 'pending',
        ];

        // Try Eloquent first, fall back to REST API
        $edition = $this->createEditionRecord($editionData);

        // Dispatch extraction job (sync queue: runs immediately)
        PdfExtractionJob::dispatch($edition);

        return $edition;
    }

    /**
     * Create an Edition from a local file path (artisan command).
     */
    public function createFromLocalPath(string $filePath, ?string $publicationDate): Edition
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $fileHash = hash_file('sha256', $filePath);

        $existing = $this->findByHash($fileHash);
        if ($existing !== null) {
            return $existing;
        }

        $filename = basename($filePath);

        if (empty($publicationDate)) {
            $publicationDate = $this->extractDateFromFilename($filename);
        }

        if (empty($publicationDate)) {
            $publicationDate = now()->toDateString();
        }

        $destPath = storage_path('app/pdfs/' . $filename);
        if (! is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }
        copy($filePath, $destPath);

        return $this->createEditionRecord([
            'filename'         => $filename,
            'file_path'        => 'pdfs/' . $filename,
            'file_hash'        => $fileHash,
            'publication_date' => $publicationDate,
            'newspaper_name'   => 'El Heraldo',
            'status'           => 'pending',
        ]);
    }

    /**
     * Find an edition by file hash.
     */
    public function findByHash(string $hash): ?Edition
    {
        // Try Eloquent first
        try {
            return Edition::where('file_hash', $hash)->first();
        } catch (\Exception $e) {
            Log::warning('EditionService: Eloquent findByHash failed, trying REST', ['error' => $e->getMessage()]);
        }

        // Fallback: REST API
        $rest = new SupabaseRestService();
        if (! $rest->available()) {
            return null;
        }

        $row = $rest->findOne('editions', ['file_hash' => 'eq.' . $hash]);
        if ($row === null) {
            return null;
        }

        return $this->hydrateEdition($row);
    }

    /**
     * Extract publication date from filename pattern EH[YYYY-MM-DD]-*.pdf
     */
    public function extractDateFromFilename(string $filename): ?string
    {
        if (preg_match('/EH(\d{4}-\d{2}-\d{2})/i', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Create an edition record, trying Eloquent first and REST API as fallback.
     *
     * @param array<string,mixed> $data
     */
    private function createEditionRecord(array $data): Edition
    {
        // Try Eloquent
        try {
            return Edition::create($data);
        } catch (\Exception $e) {
            Log::warning('EditionService: Eloquent create failed, trying REST', ['error' => $e->getMessage()]);
        }

        // Fallback: REST API
        $rest = new SupabaseRestService();
        if (! $rest->available()) {
            throw new \RuntimeException('Cannot create edition: both DB and REST API unavailable');
        }

        $now = now()->toIso8601String();
        $row = $rest->insert('editions', array_merge($data, [
            'total_articles' => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]));

        if (empty($row['id'])) {
            throw new \RuntimeException('Failed to create edition via REST API');
        }

        return $this->hydrateEdition($row);
    }

    /**
     * Build an Edition model from a raw REST API row.
     *
     * @param array<string,mixed> $row
     */
    private function hydrateEdition(array $row): Edition
    {
        $edition = new Edition();
        $edition->setRawAttributes($row);
        $edition->exists = true;
        return $edition;
    }
}

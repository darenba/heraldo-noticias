<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PdfExtractionJob;
use App\Models\Edition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EditionService
{
    /**
     * Create an Edition from an uploaded file and dispatch the extraction job.
     */
    public function createFromUpload(UploadedFile $file, ?string $publicationDate): Edition
    {
        // Compute SHA256 hash of the file contents
        $fileHash = hash_file('sha256', $file->getRealPath());

        // Check for duplicate by file hash
        $existing = $this->findByHash($fileHash);
        if ($existing !== null) {
            return $existing;
        }

        // Extract date from filename if not provided
        if (empty($publicationDate)) {
            $publicationDate = $this->extractDateFromFilename($file->getClientOriginalName());
        }

        if (empty($publicationDate)) {
            $publicationDate = now()->toDateString();
        }

        // Store file in local storage (Supabase Storage integration can be added here)
        $storedPath = $file->store('pdfs', 'local');

        // Create the edition record
        $edition = Edition::create([
            'filename' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'file_hash' => $fileHash,
            'publication_date' => $publicationDate,
            'newspaper_name' => 'El Heraldo',
            'status' => 'pending',
        ]);

        // Dispatch background job
        PdfExtractionJob::dispatch($edition);

        return $edition;
    }

    /**
     * Create an Edition from a local file path (used by artisan command).
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

        // Copy file to local storage
        $destPath = storage_path('app/pdfs/' . $filename);
        if (! is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }
        copy($filePath, $destPath);

        $edition = Edition::create([
            'filename' => $filename,
            'file_path' => 'pdfs/' . $filename,
            'file_hash' => $fileHash,
            'publication_date' => $publicationDate,
            'newspaper_name' => 'El Heraldo',
            'status' => 'pending',
        ]);

        return $edition;
    }

    /**
     * Find an edition by its file hash (duplicate detection).
     */
    public function findByHash(string $hash): ?Edition
    {
        return Edition::where('file_hash', $hash)->first();
    }

    /**
     * Extract publication date from filename using pattern EH[YYYY-MM-DD]-[hash].pdf
     */
    public function extractDateFromFilename(string $filename): ?string
    {
        if (preg_match('/EH(\d{4}-\d{2}-\d{2})/i', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

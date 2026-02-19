<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    /**
     * Extract text content page by page from a PDF file.
     *
     * @return array<int, array{page: int, text: string, error?: string}>
     */
    public function extractPages(string $filePath): array
    {
        $pages = [];

        if (! file_exists($filePath)) {
            throw new \RuntimeException("PDF file not found: {$filePath}");
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            $pdfPages = $pdf->getPages();

            foreach ($pdfPages as $index => $page) {
                $pageNumber = $index + 1;
                try {
                    $text = $page->getText();
                    $pages[] = [
                        'page' => $pageNumber,
                        'text' => $text,
                    ];
                } catch (\Exception $e) {
                    Log::warning("PdfParserService: error on page {$pageNumber}", [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                    $pages[] = [
                        'page' => $pageNumber,
                        'text' => '',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("PdfParserService: failed to parse PDF", [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $pages;
    }

    /**
     * Get total page count of a PDF without extracting text.
     */
    public function getTotalPages(string $filePath): int
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);

            return count($pdf->getPages());
        } catch (\Exception $e) {
            Log::warning("PdfParserService: could not count pages for {$filePath}: " . $e->getMessage());

            return 0;
        }
    }
}

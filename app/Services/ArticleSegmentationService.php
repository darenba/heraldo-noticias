<?php

declare(strict_types=1);

namespace App\Services;

class ArticleSegmentationService
{
    private const MIN_BODY_LENGTH = 50;

    private const KNOWN_SECTIONS = [
        'POLÍTICA', 'POLITICA',
        'ECONOMÍA', 'ECONOMIA',
        'DEPORTES',
        'CULTURA',
        'LOCAL',
        'INTERNACIONAL',
        'JUDICIAL',
        'SOCIEDAD',
        'OPINIÓN', 'OPINION',
        'NEGOCIOS',
        'SALUD',
        'EDUCACIÓN', 'EDUCACION',
        'SUCESOS',
        'NACIONAL',
        'MUNDO',
    ];

    /**
     * Segment pages of text into individual articles.
     *
     * @param  array<int, array{page: int, text: string, error?: string}>  $pages
     * @return array<int, array{title: string, body: string, body_excerpt: string, section: string|null, page_number: int, word_count: int}>
     */
    public function segment(array $pages): array
    {
        $articles = [];
        $currentTitle = null;
        $currentBody = [];
        $currentSection = null;
        $currentPage = 1;

        foreach ($pages as $pageData) {
            if (empty($pageData['text'])) {
                continue;
            }

            $lines = $this->splitLines($pageData['text']);
            $pageNumber = $pageData['page'];

            foreach ($lines as $line) {
                $trimmed = trim($line);

                if (empty($trimmed)) {
                    if (! empty($currentBody)) {
                        $currentBody[] = '';
                    }
                    continue;
                }

                // Check if this line is a section header
                $section = $this->detectSection($trimmed);
                if ($section !== null) {
                    $currentSection = $section;
                    continue;
                }

                // Check if this line is a headline (potential article title)
                if ($this->isHeadline($trimmed)) {
                    // Save the previous article if it has enough content
                    if ($currentTitle !== null) {
                        $body = implode("\n", $currentBody);
                        if (mb_strlen(trim($body)) >= self::MIN_BODY_LENGTH) {
                            $articles[] = $this->buildArticle(
                                $currentTitle,
                                $body,
                                $currentSection,
                                $currentPage
                            );
                        }
                    }

                    // Start new article
                    $currentTitle = $this->normalizeTitle($trimmed);
                    $currentBody = [];
                    $currentPage = $pageNumber;
                } else {
                    // Add line to current article body
                    if ($currentTitle !== null) {
                        $currentBody[] = $trimmed;
                    }
                }
            }
        }

        // Save the last article
        if ($currentTitle !== null) {
            $body = implode("\n", $currentBody);
            if (mb_strlen(trim($body)) >= self::MIN_BODY_LENGTH) {
                $articles[] = $this->buildArticle(
                    $currentTitle,
                    $body,
                    $currentSection,
                    $currentPage
                );
            }
        }

        return $articles;
    }

    private function isHeadline(string $line): bool
    {
        // Must be at least 20 characters
        if (mb_strlen($line) < 20) {
            return false;
        }

        // Must be mostly uppercase (at least 70% uppercase letters)
        $letters = preg_replace('/[^a-záéíóúüñA-ZÁÉÍÓÚÜÑ]/u', '', $line);
        if (empty($letters)) {
            return false;
        }

        $upperCount = mb_strlen(preg_replace('/[^A-ZÁÉÍÓÚÜÑ]/u', '', $line));
        $totalLetters = mb_strlen($letters);

        if ($totalLetters === 0) {
            return false;
        }

        $upperRatio = $upperCount / $totalLetters;

        // Reject if it looks like a date or number pattern
        if (preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}/', $line)) {
            return false;
        }

        // Reject if it's only numbers and punctuation
        if (preg_match('/^[\d\s\.\,\-\:]+$/', $line)) {
            return false;
        }

        return $upperRatio >= 0.7;
    }

    private function detectSection(string $line): ?string
    {
        $normalized = mb_strtoupper(trim($line));

        foreach (self::KNOWN_SECTIONS as $section) {
            if ($normalized === $section || $normalized === $section . ':') {
                // Normalize to proper display name
                return mb_strtoupper($section);
            }
        }

        return null;
    }

    private function normalizeTitle(string $title): string
    {
        // Remove leading/trailing punctuation and whitespace
        $title = trim($title, " \t\n\r\0\x0B.,;:-–—");

        // Collapse multiple spaces
        $title = preg_replace('/\s+/', ' ', $title);

        return $title;
    }

    /**
     * @return array{title: string, body: string, body_excerpt: string, section: string|null, page_number: int, word_count: int}
     */
    private function buildArticle(string $title, string $body, ?string $section, int $pageNumber): array
    {
        $body = trim($body);
        $bodyExcerpt = mb_substr($body, 0, 500);
        $wordCount = str_word_count(strip_tags($body));

        return [
            'title' => $title,
            'body' => $body,
            'body_excerpt' => $bodyExcerpt,
            'section' => $section,
            'page_number' => $pageNumber,
            'word_count' => $wordCount,
        ];
    }

    /**
     * Split text into lines, handling Windows/Unix line endings.
     *
     * @return string[]
     */
    private function splitLines(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

class TagGeneratorService
{
    private const STOPWORDS = [
        'de', 'la', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por',
        'un', 'para', 'con', 'una', 'su', 'al', 'es', 'lo', 'como', 'mas',
        'pero', 'sus', 'le', 'ya', 'o', 'fue', 'este', 'ha', 'si', 'porque',
        'esta', 'son', 'entre', 'cuando', 'muy', 'sin', 'sobre', 'tambien',
        'me', 'hasta', 'hay', 'donde', 'quien', 'desde', 'todo', 'nos',
        'durante', 'estados', 'todos', 'uno', 'ante', 'ellos', 'esto', 'mi',
        'antes', 'algunos', 'que', 'unos', 'yo', 'otro', 'otras', 'otra',
        'el', 'tanto', 'esa', 'estos', 'mucho', 'quienes', 'nada', 'muchos',
        'cual', 'poco', 'ella', 'estar', 'estas', 'tenia', 'anos', 'ano',
        'gobierno', 'ser', 'han', 'tienen', 'segun', 'tras', 'mas', 'aunque',
        'asi', 'mismo', 'cada', 'ayer', 'hoy', 'puede', 'bien', 'fue',
        'dijo', 'dice', 'sera', 'debe', 'tiene', 'hacer', 'tres', 'dos',
        'part', 'via', 'vez', 'dia', 'dias', 'pais', 'vez', 'solo',
        'luego', 'aun', 'sino', 'embargo', 'ademas', 'entonces', 'mientras',
        'mayor', 'menor', 'bajo', 'alto', 'gran', 'nueva', 'nuevo',
        'primera', 'primero', 'segundo', 'segunda', 'ultima', 'ultimo',
        'menos', 'dentro', 'fuera', 'hacia', 'mediante', 'cuyo', 'cuya',
        'cuyas', 'cuyos', 'nuestro', 'nuestra', 'vuestro', 'vuestra',
        'ellas', 'nosotros', 'vosotros', 'ustedes', 'suyo', 'suya',
    ];

    private const ACCENT_MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'Ü' => 'u', 'Ñ' => 'n',
    ];

    /**
     * Generate tags from text using simplified TF-IDF frequency analysis.
     *
     * @return array<int, array{name: string, display_name: string, score: float}>
     */
    public function generate(string $text, int $limit = 8): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Step 1: lowercase
        $normalized = mb_strtolower($text);

        // Step 2: remove accents
        $normalized = strtr($normalized, self::ACCENT_MAP);

        // Step 3: remove punctuation and special chars, keep only letters and spaces
        $normalized = preg_replace('/[^a-z\s]/u', ' ', $normalized);

        // Step 4: split into words
        $words = preg_split('/\s+/', trim($normalized));
        $words = array_filter($words, fn ($w) => ! empty($w));

        // Step 5: filter stopwords and short words (< 4 chars)
        $filtered = array_filter($words, function (string $word): bool {
            return mb_strlen($word) >= 4 && ! in_array($word, self::STOPWORDS, true);
        });

        if (empty($filtered)) {
            return [];
        }

        // Step 6: count frequency
        $frequency = array_count_values(array_values($filtered));

        // Step 7: sort by frequency descending
        arsort($frequency);

        // Step 8: take top $limit words
        $topWords = array_slice($frequency, 0, $limit, true);

        $totalWords = count($words) ?: 1;

        // Step 9: build result array
        $tags = [];
        foreach ($topWords as $word => $count) {
            $score = $count / $totalWords;
            $tags[] = [
                'name' => $word,
                'display_name' => $this->toDisplayName($word, $text),
                'score' => round($score, 4),
            ];
        }

        return $tags;
    }

    /**
     * Try to find the original (accented) version of the word in the source text.
     */
    private function toDisplayName(string $normalizedWord, string $originalText): string
    {
        // Search case-insensitively for the word with accents in original text
        if (preg_match('/\b([a-záéíóúüñA-ZÁÉÍÓÚÜÑ]+)\b/u', $originalText, $matches)) {
            $normalized = mb_strtolower(strtr($matches[1], self::ACCENT_MAP));
            if ($normalized === $normalizedWord) {
                return mb_strtolower($matches[1]);
            }
        }

        // Fallback: return the normalized word
        return $normalizedWord;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Extracts and classifies newspaper articles from raw PDF text using Claude AI.
 * Falls back gracefully to an empty array if the API is unavailable.
 */
class ClaudeExtractionService
{
    private const API_URL        = 'https://api.anthropic.com/v1/messages';
    private const MODEL          = 'claude-haiku-4-5-20251001';
    private const MAX_TOKENS     = 8192;
    private const PAGES_PER_CHUNK = 4;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.api_key', '');
    }

    public function available(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Extract all articles from the given pages using Claude AI.
     *
     * @param  array<int, array{page: int, text: string, error?: string}> $pages
     * @param  string $publicationDate  e.g. "2026-02-19"
     * @param  string $newspaperName
     * @return array<int, array{title: string, body: string, body_excerpt: string, section: string|null, page_number: int, word_count: int, tags: string[]}>
     */
    public function extractArticles(array $pages, string $publicationDate, string $newspaperName): array
    {
        if (! $this->available()) {
            Log::warning('ClaudeExtractionService: ANTHROPIC_API_KEY not configured');
            return [];
        }

        // Process pages in chunks to stay within context limits
        $chunks  = array_chunk($pages, self::PAGES_PER_CHUNK);
        $articles = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $chunkArticles = $this->processChunk($chunk, $publicationDate, $newspaperName);
                $articles = array_merge($articles, $chunkArticles);

                Log::info('ClaudeExtractionService: chunk processed', [
                    'chunk'    => $chunkIndex + 1,
                    'of'       => count($chunks),
                    'articles' => count($chunkArticles),
                ]);
            } catch (\Exception $e) {
                Log::warning('ClaudeExtractionService: chunk failed', [
                    'chunk' => $chunkIndex + 1,
                    'error' => $e->getMessage(),
                ]);
                // Continue with next chunk even if one fails
            }
        }

        return $articles;
    }

    /**
     * @param  array<int, array{page: int, text: string, error?: string}> $pages
     * @return array<int, array{title: string, body: string, body_excerpt: string, section: string|null, page_number: int, word_count: int, tags: string[]}>
     */
    private function processChunk(array $pages, string $publicationDate, string $newspaperName): array
    {
        $prompt   = $this->buildPrompt($pages, $publicationDate, $newspaperName);
        $response = $this->callClaudeApi($prompt);

        return $this->parseResponse($response, $pages);
    }

    private function buildPrompt(array $pages, string $publicationDate, string $newspaperName): string
    {
        $pagesText = '';
        foreach ($pages as $pageData) {
            $text = trim($pageData['text'] ?? '');
            if (empty($text)) {
                continue;
            }
            $pagesText .= "\n\n--- PÁGINA {$pageData['page']} ---\n" . $text;
        }

        if (empty(trim($pagesText))) {
            throw new \RuntimeException('No text content in pages chunk');
        }

        return <<<PROMPT
Eres un extractor experto de artículos de periódico latinoamericano. Se te proporcionará texto bruto extraído página por página de una edición de "{$newspaperName}" del {$publicationDate}.

Tu tarea es identificar TODOS los artículos periodísticos individuales en el texto y extraer sus datos estructurados.

INSTRUCCIONES:
- Un artículo comienza con su TITULAR (normalmente en mayúsculas o muy destacado).
- El cuerpo del artículo es el texto que sigue al titular hasta el próximo titular.
- Ignora: avisos publicitarios, esquelas, tablas de números, cabeceras de sección sueltas, fechas y números de página aislados.
- Si el texto de la página no contiene artículos legibles, devuelve un array vacío [].
- Para "section", usa SOLO uno de estos valores exactos o null: POLITICA, ECONOMIA, DEPORTES, CULTURA, LOCAL, INTERNACIONAL, JUDICIAL, SOCIEDAD, OPINION, NEGOCIOS, SALUD, EDUCACION, SUCESOS, NACIONAL, MUNDO.
- "tags" debe ser un array de 5 a 8 palabras clave relevantes, en minúsculas, sin acentos, sin artículos.
- "body_excerpt" son los primeros 300 caracteres del body.
- "word_count" es la cantidad de palabras del body.

FORMATO DE RESPUESTA:
Devuelve ÚNICAMENTE un array JSON válido, sin texto antes ni después, sin explicaciones, sin markdown. Ejemplo:
[
  {
    "title": "TITULAR DEL ARTÍCULO EN MAYÚSCULAS",
    "body": "Texto completo del artículo...",
    "body_excerpt": "Primeros 300 caracteres...",
    "section": "POLITICA",
    "page_number": 3,
    "word_count": 245,
    "tags": ["palabra1", "palabra2", "palabra3", "palabra4", "palabra5"]
  }
]

TEXTO DE LAS PÁGINAS:
{$pagesText}

Responde solo con el JSON array:
PROMPT;
    }

    /**
     * Call the Anthropic Claude API.
     *
     * @throws \RuntimeException on HTTP or JSON errors
     */
    private function callClaudeApi(string $prompt): string
    {
        $payload = json_encode([
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ]),
            'content'       => $payload,
            'timeout'       => 55, // Stay under Vercel's 60s function limit
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents(self::API_URL, false, $context);

        if ($body === false) {
            throw new \RuntimeException('Failed to connect to Anthropic API');
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from Anthropic API');
        }

        if (isset($data['error'])) {
            throw new \RuntimeException('Anthropic API error: ' . ($data['error']['message'] ?? 'unknown'));
        }

        $content = $data['content'][0]['text'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('Empty content from Anthropic API');
        }

        return $content;
    }

    /**
     * Parse the Claude JSON response into a structured array.
     *
     * @param  array<int, array{page: int, text: string}> $pages  Original pages for fallback page numbers
     * @return array<int, array{title: string, body: string, body_excerpt: string, section: string|null, page_number: int, word_count: int, tags: string[]}>
     */
    private function parseResponse(string $response, array $pages): array
    {
        // Strip potential markdown code blocks
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $response);
        $clean = preg_replace('/\s*```$/m', '', $clean);
        $clean = trim($clean ?? $response);

        // Find the JSON array in the response (in case Claude adds extra text)
        if (! str_starts_with($clean, '[')) {
            $start = strpos($clean, '[');
            $end   = strrpos($clean, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $clean = substr($clean, $start, $end - $start + 1);
            }
        }

        $articles = json_decode($clean, true);

        if (! is_array($articles)) {
            Log::warning('ClaudeExtractionService: could not parse response as JSON', [
                'response_preview' => mb_substr($response, 0, 200),
            ]);
            return [];
        }

        $validSections = [
            'POLITICA', 'ECONOMIA', 'DEPORTES', 'CULTURA', 'LOCAL',
            'INTERNACIONAL', 'JUDICIAL', 'SOCIEDAD', 'OPINION',
            'NEGOCIOS', 'SALUD', 'EDUCACION', 'SUCESOS', 'NACIONAL', 'MUNDO',
        ];

        $firstPage = $pages[0]['page'] ?? 1;
        $result    = [];

        foreach ($articles as $item) {
            if (! is_array($item) || empty($item['title']) || empty($item['body'])) {
                continue;
            }

            $title  = trim((string) ($item['title'] ?? ''));
            $body   = trim((string) ($item['body'] ?? ''));

            if (empty($title) || mb_strlen($body) < 30) {
                continue;
            }

            $section    = strtoupper((string) ($item['section'] ?? ''));
            $section    = in_array($section, $validSections, true) ? $section : null;

            $pageNumber = (int) ($item['page_number'] ?? $firstPage);
            $wordCount  = (int) ($item['word_count'] ?? str_word_count(strip_tags($body)));
            $bodyExcerpt = mb_substr($body, 0, 300);

            $tags = [];
            if (is_array($item['tags'] ?? null)) {
                foreach ($item['tags'] as $t) {
                    $tag = strtolower(trim((string) $t));
                    if (! empty($tag) && mb_strlen($tag) >= 3) {
                        $tags[] = $tag;
                    }
                }
            }

            $result[] = [
                'title'        => $title,
                'body'         => $body,
                'body_excerpt' => $bodyExcerpt,
                'section'      => $section,
                'page_number'  => $pageNumber,
                'word_count'   => $wordCount,
                'tags'         => array_slice($tags, 0, 8),
            ];
        }

        return $result;
    }
}

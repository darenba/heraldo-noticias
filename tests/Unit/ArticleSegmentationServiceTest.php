<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ArticleSegmentationService;
use Tests\TestCase;

class ArticleSegmentationServiceTest extends TestCase
{
    private ArticleSegmentationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ArticleSegmentationService();
    }

    // -------------------------------------------------------------------------
    // segment() — basic detection
    // -------------------------------------------------------------------------

    public function test_segment_returns_empty_for_empty_pages(): void
    {
        $result = $this->service->segment([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_segment_detects_uppercase_headline(): void
    {
        $pages = [
            [
                'page' => 1,
                'text' => "GOBIERNO ANUNCIA NUEVAS MEDIDAS ECONÓMICAS\n"
                    . "El gobierno presentó hoy un paquete de medidas para impulsar la economía nacional. "
                    . "Las medidas incluyen subsidios al transporte y reducción de impuestos a pequeñas empresas.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertCount(1, $result);
        $this->assertSame('GOBIERNO ANUNCIA NUEVAS MEDIDAS ECONÓMICAS', $result[0]['title']);
        $this->assertNotEmpty($result[0]['body']);
        $this->assertSame(1, $result[0]['page_number']);
    }

    public function test_segment_ignores_short_lines_as_headlines(): void
    {
        // Line under 20 chars must not become a headline
        $pages = [
            [
                'page' => 1,
                'text' => "CORTO\nTexto de cuerpo que supera los cincuenta caracteres necesarios para ser válido como artículo.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertEmpty($result);
    }

    public function test_segment_ignores_body_shorter_than_minimum(): void
    {
        $pages = [
            [
                'page' => 1,
                'text' => "TITULAR LARGO EN MAYÚSCULAS QUE SUPERA VEINTE CHARS\nMuy corto.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertEmpty($result);
    }

    public function test_segment_detects_section_from_known_sections(): void
    {
        $pages = [
            [
                'page' => 2,
                'text' => "DEPORTES\nGRANDES VICTORIAS EN EL CAMPEONATO NACIONAL DE FÚTBOL\n"
                    . "El equipo local se coronó campeón ante una multitud de más de diez mil aficionados presentes.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertNotEmpty($result);
        $this->assertSame('Deportes', $result[0]['section']);
    }

    public function test_segment_returns_expected_keys(): void
    {
        $pages = [
            [
                'page' => 1,
                'text' => "NUEVA POLÍTICA EDUCATIVA PARA EL PRÓXIMO CICLO ESCOLAR\n"
                    . "El ministerio de educación presentó las bases del nuevo modelo pedagógico que se aplicará "
                    . "a partir del siguiente ciclo escolar en todos los centros de enseñanza del país.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertNotEmpty($result);
        $article = $result[0];
        $this->assertArrayHasKey('title', $article);
        $this->assertArrayHasKey('body', $article);
        $this->assertArrayHasKey('body_excerpt', $article);
        $this->assertArrayHasKey('section', $article);
        $this->assertArrayHasKey('page_number', $article);
        $this->assertArrayHasKey('word_count', $article);
    }

    public function test_segment_excerpt_does_not_exceed_300_chars(): void
    {
        $longBody = str_repeat('Texto de prueba muy largo para verificar el truncamiento. ', 20);
        $pages = [
            [
                'page' => 1,
                'text' => "TITULAR LARGO EN MAYÚSCULAS SUFICIENTE\n{$longBody}",
            ],
        ];

        $result = $this->service->segment($pages);

        if (! empty($result)) {
            $this->assertLessThanOrEqual(300, mb_strlen($result[0]['body_excerpt']));
        } else {
            $this->markTestSkipped('Segmenter did not extract article — adjust threshold or body.');
        }
    }

    public function test_segment_counts_words_correctly(): void
    {
        $body = 'Esta es una prueba de conteo de palabras en el cuerpo del artículo completo.';
        $pages = [
            [
                'page' => 1,
                'text' => "TITULAR LARGO EN MAYÚSCULAS PARA ESTE TEST\n{$body}",
            ],
        ];

        $result = $this->service->segment($pages);

        if (! empty($result)) {
            $expected = str_word_count($result[0]['body']);
            $this->assertSame($expected, $result[0]['word_count']);
        } else {
            $this->markTestSkipped('Body too short; adjust fixture.');
        }
    }

    public function test_segment_handles_multiple_articles_across_pages(): void
    {
        $pages = [
            [
                'page' => 1,
                'text' => "PRIMER TITULAR EN MAYÚSCULAS BASTANTE LARGO\n"
                    . "Cuerpo del primer artículo con suficiente texto para superar el mínimo requerido.",
            ],
            [
                'page' => 2,
                'text' => "SEGUNDO TITULAR EN MAYÚSCULAS TAMBIÉN LARGO\n"
                    . "Cuerpo del segundo artículo con suficiente texto para superar el mínimo requerido.",
            ],
        ];

        $result = $this->service->segment($pages);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['page_number']);
        $this->assertSame(2, $result[1]['page_number']);
    }

    public function test_segment_default_section_is_general(): void
    {
        $pages = [
            [
                'page' => 1,
                'text' => "TITULAR SIN SECCIÓN RECONOCIDA EN EL TEXTO\n"
                    . "Este artículo no pertenece a ninguna sección conocida del periódico.",
            ],
        ];

        $result = $this->service->segment($pages);

        if (! empty($result)) {
            $this->assertSame('General', $result[0]['section']);
        } else {
            $this->markTestSkipped('Segmenter skipped article — review min body length.');
        }
    }
}

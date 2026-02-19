<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TagGeneratorService;
use Tests\TestCase;

class TagGeneratorServiceTest extends TestCase
{
    private TagGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagGeneratorService();
    }

    // -------------------------------------------------------------------------
    // generate() — basic output shape
    // -------------------------------------------------------------------------

    public function test_generate_returns_array(): void
    {
        $result = $this->service->generate('El gobierno anunció nuevas políticas económicas para el país.');

        $this->assertIsArray($result);
    }

    public function test_generate_returns_expected_keys_per_tag(): void
    {
        $result = $this->service->generate('El gobierno anunció nuevas políticas económicas para el país.');

        foreach ($result as $tag) {
            $this->assertArrayHasKey('name', $tag);
            $this->assertArrayHasKey('display_name', $tag);
            $this->assertArrayHasKey('score', $tag);
        }
    }

    public function test_generate_respects_limit_parameter(): void
    {
        $text = 'El presidente anunció medidas económicas para el sector industrial. '
            . 'Los trabajadores industriales recibirán beneficios económicos adicionales. '
            . 'El sector económico espera crecimiento sostenido durante el año fiscal. '
            . 'Las empresas industriales reportaron ganancias en el trimestre fiscal.';

        $result = $this->service->generate($text, 3);

        $this->assertLessThanOrEqual(3, count($result));
    }

    public function test_generate_filters_stopwords(): void
    {
        // Common Spanish stopwords that must never appear as tags
        $stopwords = ['de', 'el', 'la', 'los', 'las', 'un', 'una', 'y', 'en', 'que', 'con', 'por'];

        $result = $this->service->generate(
            'El gobierno anunció de la nueva política económica con los ciudadanos.'
        );

        $names = array_column($result, 'name');
        foreach ($stopwords as $sw) {
            $this->assertNotContains($sw, $names, "Stopword '{$sw}' must be filtered out.");
        }
    }

    public function test_generate_returns_lowercase_name(): void
    {
        $result = $this->service->generate('El Gobierno anunció Medidas Económicas importantes.');

        foreach ($result as $tag) {
            $this->assertSame(strtolower($tag['name']), $tag['name']);
        }
    }

    public function test_generate_returns_empty_for_empty_string(): void
    {
        $result = $this->service->generate('');

        $this->assertEmpty($result);
    }

    public function test_generate_returns_empty_for_stopwords_only_text(): void
    {
        $result = $this->service->generate('el de la los y en que con por un una');

        $this->assertEmpty($result);
    }

    public function test_generate_scores_are_positive(): void
    {
        $result = $this->service->generate('La economía nacional creció este año fiscal.');

        foreach ($result as $tag) {
            $this->assertGreaterThan(0, $tag['score']);
        }
    }

    public function test_generate_scores_in_descending_order(): void
    {
        $text = 'El gobierno gobierno gobierno aprobó la ley ley para la economía economía.';

        $result = $this->service->generate($text, 8);

        $scores = array_column($result, 'score');
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores, 'Tags must be ordered by score descending.');
    }

    public function test_generate_strips_accents_for_name(): void
    {
        $result = $this->service->generate('La educación pública necesita más financiación urgente.');

        $names = array_column($result, 'name');
        foreach ($names as $name) {
            // name field must not contain accented chars (normalized)
            $this->assertSame(
                preg_replace('/[áéíóúüñ]/u', '', $name),
                $name,
                "Tag name '{$name}' should not contain accented characters."
            );
        }
    }

    public function test_generate_display_name_may_preserve_accents(): void
    {
        // display_name can be the original form; just ensure it's a non-empty string
        $result = $this->service->generate('La educación pública necesita más financiación urgente.');

        foreach ($result as $tag) {
            $this->assertIsString($tag['display_name']);
            $this->assertNotEmpty($tag['display_name']);
        }
    }

    public function test_generate_does_not_repeat_tags(): void
    {
        $text = str_repeat('gobierno economía política ', 10);

        $result = $this->service->generate($text, 8);

        $names = array_column($result, 'name');
        $this->assertSame(array_unique($names), $names, 'Returned tags must not contain duplicates.');
    }
}

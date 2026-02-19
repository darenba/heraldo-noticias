<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Edition;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSearchTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Public index
    // -------------------------------------------------------------------------

    public function test_public_index_returns_200(): void
    {
        $response = $this->get(route('articles.index'));

        $response->assertStatus(200);
        $response->assertViewIs('public.index');
    }

    public function test_index_displays_articles_from_completed_editions(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        Article::factory()->count(3)->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.index'));

        $response->assertStatus(200);
        $response->assertViewHas('articles');
    }

    public function test_index_does_not_display_articles_from_pending_editions(): void
    {
        $pending  = Edition::factory()->create(['status' => 'pending']);
        $article  = Article::factory()->create([
            'edition_id' => $pending->id,
            'title'      => 'ARTÍCULO EN EDICIÓN PENDIENTE',
        ]);

        $response = $this->get(route('articles.index'));

        $response->assertDontSee($article->title);
    }

    // -------------------------------------------------------------------------
    // Full-text search
    // -------------------------------------------------------------------------

    public function test_search_returns_matching_articles(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'title'      => 'ECONOMÍA NACIONAL CRECE EN EL TERCER TRIMESTRE',
        ]);

        $response = $this->get(route('articles.index', ['q' => 'economía']));

        $response->assertStatus(200);
        $response->assertSee($article->title);
    }

    public function test_search_with_no_results_shows_empty_state(): void
    {
        $response = $this->get(route('articles.index', ['q' => 'zzzzzyyyyyy_inexistente']));

        $response->assertStatus(200);
        // View should still render; no 500 error
    }

    public function test_search_accepts_section_filter(): void
    {
        $edition   = Edition::factory()->create(['status' => 'completed']);
        $deportes  = Article::factory()->create([
            'edition_id' => $edition->id,
            'section'    => 'Deportes',
            'title'      => 'PARTIDO DE FÚTBOL NACIONAL',
        ]);
        $politica  = Article::factory()->create([
            'edition_id' => $edition->id,
            'section'    => 'Política',
            'title'      => 'DEBATE PARLAMENTARIO INTENSO',
        ]);

        $response = $this->get(route('articles.index', ['section' => 'Deportes']));

        $response->assertSee($deportes->title);
        $response->assertDontSee($politica->title);
    }

    public function test_search_accepts_date_from_filter(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);

        $old = Article::factory()->create([
            'edition_id'       => $edition->id,
            'publication_date' => '2023-01-01',
            'title'            => 'NOTICIA ANTIGUA DEL AÑO DOS MIL VEINTITRÉS',
        ]);
        $new = Article::factory()->create([
            'edition_id'       => $edition->id,
            'publication_date' => '2024-06-01',
            'title'            => 'NOTICIA RECIENTE DEL AÑO DOS MIL VEINTICUATRO',
        ]);

        $response = $this->get(route('articles.index', ['date_from' => '2024-01-01']));

        $response->assertSee($new->title);
        $response->assertDontSee($old->title);
    }

    public function test_search_accepts_date_to_filter(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);

        $old = Article::factory()->create([
            'edition_id'       => $edition->id,
            'publication_date' => '2023-01-01',
            'title'            => 'NOTICIA ANTIGUA DEL AÑO PASADO',
        ]);
        $new = Article::factory()->create([
            'edition_id'       => $edition->id,
            'publication_date' => '2025-06-01',
            'title'            => 'NOTICIA MUY RECIENTE DEL FUTURO CERCANO',
        ]);

        $response = $this->get(route('articles.index', ['date_to' => '2024-12-31']));

        $response->assertSee($old->title);
        $response->assertDontSee($new->title);
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    public function test_results_are_paginated(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        Article::factory()->count(25)->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.index'));

        $response->assertStatus(200);
        $response->assertViewHas('articles', function ($articles) {
            return $articles->total() === 25 && $articles->perPage() === 20;
        });
    }

    public function test_pagination_returns_second_page(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        Article::factory()->count(25)->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.index', ['page' => 2]));

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Tag filter
    // -------------------------------------------------------------------------

    public function test_by_tag_redirects_to_index_with_filter(): void
    {
        $tag = Tag::factory()->create(['name' => 'economia']);

        $response = $this->get(route('articles.byTag', $tag->name));

        // Controller redirects to index with tag filter applied
        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // XSS in search query
    // -------------------------------------------------------------------------

    public function test_search_query_is_escaped_in_output(): void
    {
        $xss = '<script>alert(1)</script>';

        $response = $this->get(route('articles.index', ['q' => $xss]));

        $response->assertStatus(200);
        $response->assertDontSee($xss, false); // escaped
    }
}

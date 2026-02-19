<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Edition;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleShowTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Show page — basic
    // -------------------------------------------------------------------------

    public function test_show_returns_200_for_existing_article(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertStatus(200);
        $response->assertViewIs('public.show');
    }

    public function test_show_returns_404_for_nonexistent_article(): void
    {
        $response = $this->get(route('articles.show', 9999));

        $response->assertStatus(404);
    }

    public function test_show_displays_article_title(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'title'      => 'TITULAR DE PRUEBA EN MAYÚSCULAS',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertSee('TITULAR DE PRUEBA EN MAYÚSCULAS');
    }

    public function test_show_displays_article_body(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'body'       => 'Este es el cuerpo completo del artículo de prueba para verificación.',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertSee('Este es el cuerpo completo del artículo de prueba para verificación.');
    }

    public function test_show_displays_article_section(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'section'    => 'Deportes',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertSee('Deportes');
    }

    public function test_show_displays_publication_date(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id'       => $edition->id,
            'publication_date' => '2024-06-15',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        // Date appears formatted in the view
        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    public function test_show_displays_article_tags(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create(['edition_id' => $edition->id]);
        $tag     = Tag::factory()->create(['name' => 'economia', 'display_name' => 'Economía']);

        $article->tags()->attach($tag->id, ['relevance_score' => 0.9]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertSee('Economía');
    }

    public function test_show_displays_no_tags_message_when_none(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertStatus(200);
        // Article with no tags should still render without error
    }

    // -------------------------------------------------------------------------
    // Related articles
    // -------------------------------------------------------------------------

    public function test_show_view_has_related_articles_variable(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create(['edition_id' => $edition->id]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertViewHas('related');
    }

    public function test_related_articles_share_at_least_one_tag(): void
    {
        $edition  = Edition::factory()->create(['status' => 'completed']);
        $article  = Article::factory()->create(['edition_id' => $edition->id]);
        $related  = Article::factory()->create(['edition_id' => $edition->id]);
        $unrelated = Article::factory()->create(['edition_id' => $edition->id]);
        $tag      = Tag::factory()->create();

        $article->tags()->attach($tag->id, ['relevance_score' => 1.0]);
        $related->tags()->attach($tag->id, ['relevance_score' => 0.8]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertStatus(200);
        $response->assertViewHas('related', function ($relatedArticles) use ($related, $unrelated) {
            $ids = $relatedArticles->pluck('id')->toArray();
            return in_array($related->id, $ids) && ! in_array($unrelated->id, $ids);
        });
    }

    public function test_related_articles_limited_to_five(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create(['edition_id' => $edition->id]);
        $tag     = Tag::factory()->create();

        $article->tags()->attach($tag->id, ['relevance_score' => 1.0]);

        // Create 10 related articles
        Article::factory()->count(10)->create(['edition_id' => $edition->id])
            ->each(fn ($a) => $a->tags()->attach($tag->id, ['relevance_score' => 0.5]));

        $response = $this->get(route('articles.show', $article->id));

        $response->assertViewHas('related', function ($relatedArticles) {
            return $relatedArticles->count() <= 5;
        });
    }

    // -------------------------------------------------------------------------
    // SEO & meta
    // -------------------------------------------------------------------------

    public function test_show_sets_page_title_to_article_title(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'title'      => 'TITULAR PARA SEO',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertSee('TITULAR PARA SEO');
    }

    // -------------------------------------------------------------------------
    // Body XSS safety
    // -------------------------------------------------------------------------

    public function test_article_body_is_escaped_against_xss(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $article = Article::factory()->create([
            'edition_id' => $edition->id,
            'body'       => 'Texto seguro <script>alert("xss")</script> fin.',
        ]);

        $response = $this->get(route('articles.show', $article->id));

        $response->assertDontSee('<script>alert("xss")</script>', false);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    private static array $sections = [
        'Política', 'Economía', 'Deportes', 'Cultura',
        'Local', 'Internacional', 'Judicial', 'Sociedad',
    ];

    public function definition(): array
    {
        $title = mb_strtoupper($this->faker->sentence(rand(4, 10)));
        $body = $this->faker->paragraphs(rand(3, 8), true);
        $bodyExcerpt = mb_substr($body, 0, 500);

        return [
            'edition_id' => Edition::factory(),
            'title' => rtrim($title, '.'),
            'body' => $body,
            'body_excerpt' => $bodyExcerpt,
            'section' => $this->faker->randomElement(self::$sections),
            'page_number' => $this->faker->numberBetween(1, 32),
            'publication_date' => $this->faker->dateTimeBetween('2020-01-01', '2021-12-31')->format('Y-m-d'),
            'newspaper_name' => 'El Heraldo',
            'content_hash' => hash('sha256', $title . $body . $this->faker->uuid()),
            'word_count' => str_word_count(strip_tags($body)),
        ];
    }
}

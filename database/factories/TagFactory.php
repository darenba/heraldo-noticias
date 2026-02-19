<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    private static array $newsTags = [
        'gobierno', 'congreso', 'economia', 'politica', 'elecciones',
        'corrupcion', 'seguridad', 'salud', 'educacion', 'cultura',
        'deporte', 'futbol', 'municipio', 'presupuesto', 'reforma',
        'internacional', 'local', 'judicial', 'sentencia', 'investigacion',
        'infraestructura', 'medio ambiente', 'empresa', 'finanzas', 'social',
    ];

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(self::$newsTags);

        return [
            'name' => $name,
            'display_name' => $name,
            'article_count' => $this->faker->numberBetween(1, 50),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

class EditionFactory extends Factory
{
    protected $model = Edition::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('2020-01-01', '2021-12-31');
        $dateStr = $date->format('Y-m-d');
        $hash = $this->faker->bothify('??????????????????????????');
        $filename = "EH{$dateStr}-{$hash}.pdf";

        return [
            'filename' => $filename,
            'file_path' => 'pdfs/' . $filename,
            'file_hash' => hash('sha256', $filename . $this->faker->uuid()),
            'publication_date' => $dateStr,
            'newspaper_name' => 'El Heraldo',
            'total_pages' => $this->faker->numberBetween(20, 40),
            'total_articles' => $this->faker->numberBetween(10, 60),
            'status' => $this->faker->randomElement(['completed', 'completed', 'completed', 'error', 'pending']),
            'processing_log' => null,
            'processed_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'total_articles' => 0]);
    }
}

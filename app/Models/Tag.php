<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'display_name',
        'article_count',
    ];

    protected function casts(): array
    {
        return [
            'article_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Tag $tag) {
            $tag->created_at = now();
        });
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tag')
            ->withPivot('relevance_score');
    }
}

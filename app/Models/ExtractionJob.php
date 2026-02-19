<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionJob extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'edition_id',
        'status',
        'page_current',
        'page_total',
        'articles_extracted',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (ExtractionJob $job) {
            $job->created_at = now();
        });
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}

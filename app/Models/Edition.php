<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'file_path',
        'file_hash',
        'publication_date',
        'newspaper_name',
        'total_pages',
        'total_articles',
        'status',
        'processing_log',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'processing_log' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function extractionJobs(): HasMany
    {
        return $this->hasMany(ExtractionJob::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}

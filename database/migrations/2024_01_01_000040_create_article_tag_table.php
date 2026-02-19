<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->float('relevance_score')->default(0);

            $table->primary(['article_id', 'tag_id']);
        });
        // No timestamps on pivot table
    }

    public function down(): void
    {
        Schema::dropIfExists('article_tag');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('body_excerpt', 500)->nullable();
            $table->string('section')->nullable();
            $table->integer('page_number')->nullable();
            $table->date('publication_date');
            $table->string('newspaper_name');
            $table->string('content_hash', 64)->unique();
            $table->integer('word_count')->default(0);
            $table->timestamps();

            $table->index('publication_date');
            $table->index('section');
            $table->index('edition_id');
            $table->index('content_hash');
        });

        // Add tsvector column for full-text search (PostgreSQL specific)
        DB::statement('ALTER TABLE articles ADD COLUMN search_vector tsvector');

        // GIN index for fast full-text search
        DB::statement('CREATE INDEX articles_search_vector_gin ON articles USING GIN(search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};

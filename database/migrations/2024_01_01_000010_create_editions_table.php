<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('file_path');
            $table->string('file_hash', 64)->unique();
            $table->date('publication_date');
            $table->string('newspaper_name')->default('El Heraldo');
            $table->integer('total_pages')->nullable();
            $table->integer('total_articles')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, error
            $table->jsonb('processing_log')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('publication_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};

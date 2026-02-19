<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->integer('page_current')->nullable();
            $table->integer('page_total')->nullable();
            $table->integer('articles_extracted')->default(0);
            $table->jsonb('errors')->default('[]');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('edition_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_jobs');
    }
};

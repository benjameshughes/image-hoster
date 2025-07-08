<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duplicate_of_id')->constrained('media')->cascadeOnDelete();
            $table->decimal('similarity_score', 5, 2);
            $table->string('detection_type'); // hash, perceptual, filename
            $table->string('action')->default('pending'); // pending, keep_both, keep_original, keep_new, merge
            $table->json('comparison_data')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            
            $table->index(['media_id', 'action']);
            $table->index(['duplicate_of_id', 'action']);
            $table->index('detection_type');
            $table->index('similarity_score');
            $table->index('created_at');
            
            // Ensure unique combination of media_id and duplicate_of_id
            $table->unique(['media_id', 'duplicate_of_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_reviews');
    }
};
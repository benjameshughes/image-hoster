<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->string('source_id');
            $table->string('source_url', 1000);
            $table->string('title')->nullable();
            $table->json('source_metadata');
            $table->foreignId('media_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['import_id', 'status']);
            $table->index('source_id');
            $table->index('media_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config'); // Stores all import settings
            $table->boolean('is_default')->default(false);
            $table->boolean('is_global')->default(false); // Admin-created presets for all users
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
            $table->index('is_global');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_presets');
    }
};
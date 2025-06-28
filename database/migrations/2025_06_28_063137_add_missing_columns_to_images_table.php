<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->integer('width')->nullable()->after('size');
            $table->integer('height')->nullable()->after('width');
            $table->string('file_hash', 64)->nullable()->after('height');
            $table->json('metadata')->nullable()->after('file_hash');

            // Add index for file hash to prevent duplicates
            $table->index('file_hash');

            // Add index for user_id if it doesn't exist
            $table->index('user_id');

            // Add index for common queries
            $table->index(['user_id', 'created_at']);
            $table->index('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex(['file_hash']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['mime_type']);

            $table->dropColumn(['width', 'height', 'file_hash', 'metadata']);
        });
    }
};

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
            // Add missing columns
            $table->unsignedBigInteger('user_id')->nullable()->after('is_public');
            $table->string('image_type')->nullable()->after('mime_type');
            
            // Rename columns to match model expectations
            $table->renameColumn('filename', 'name');
            $table->renameColumn('original_filename', 'original_name');
            
            // Add indexes for better performance
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
            
            // Add foreign key constraint for user_id
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['user_id']);
            
            // Drop indexes
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            
            // Drop added columns
            $table->dropColumn(['user_id', 'image_type']);
            
            // Rename columns back
            $table->renameColumn('name', 'filename');
            $table->renameColumn('original_name', 'original_filename');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('images', 'media');
        
        Schema::table('media', function (Blueprint $table) {
            // Add new columns for media support
            $table->string('media_type')->after('mime_type')->default('image');
            $table->string('duration')->nullable()->after('height'); // For video/audio
            $table->integer('bitrate')->nullable()->after('duration'); // For video/audio
            $table->integer('pages')->nullable()->after('bitrate'); // For documents
            
            // Add duplicate detection columns
            $table->string('perceptual_hash', 64)->nullable()->after('file_hash');
            $table->string('duplicate_status')->default('unique')->after('perceptual_hash');
            $table->unsignedBigInteger('duplicate_of_id')->nullable()->after('duplicate_status');
            $table->decimal('similarity_score', 5, 2)->nullable()->after('duplicate_of_id');
            
            // Add WordPress import tracking
            $table->string('source')->nullable()->after('similarity_score');
            $table->string('source_id')->nullable()->after('source');
            $table->json('source_metadata')->nullable()->after('source_id');
            
            // Add indexes
            $table->index('media_type');
            $table->index('perceptual_hash');
            $table->index('duplicate_status');
            $table->index(['source', 'source_id']);
            
            // Add foreign key for duplicate_of_id
            $table->foreign('duplicate_of_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['duplicate_of_id']);
            
            // Drop indexes
            $table->dropIndex(['media_type']);
            $table->dropIndex(['perceptual_hash']);
            $table->dropIndex(['duplicate_status']);
            $table->dropIndex(['source', 'source_id']);
            
            // Drop columns
            $table->dropColumn([
                'media_type',
                'duration',
                'bitrate',
                'pages',
                'perceptual_hash',
                'duplicate_status',
                'duplicate_of_id',
                'similarity_score',
                'source',
                'source_id',
                'source_metadata',
            ]);
        });
        
        Schema::rename('media', 'images');
    }
};
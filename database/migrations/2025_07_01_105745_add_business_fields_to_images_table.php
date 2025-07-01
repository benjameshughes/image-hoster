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
            // Business-focused fields
            $table->string('unique_id', 32)->nullable()->after('id')->unique();
            $table->string('slug')->nullable()->after('unique_id');
            
            // Thumbnail and compression fields
            $table->string('thumbnail_path')->nullable()->after('path');
            $table->string('compressed_path')->nullable()->after('thumbnail_path');
            $table->integer('thumbnail_width')->nullable()->after('compressed_path');
            $table->integer('thumbnail_height')->nullable()->after('thumbnail_width');
            $table->integer('compressed_size')->nullable()->after('thumbnail_height');
            
            // Sharing and visibility
            $table->boolean('is_shareable')->default(true)->after('is_public');
            $table->timestamp('shared_at')->nullable()->after('is_shareable');
            $table->integer('view_count')->default(0)->after('shared_at');
            
            // Additional metadata for business use
            $table->string('alt_text')->nullable()->after('view_count');
            $table->text('description')->nullable()->after('alt_text');
            $table->json('tags')->nullable()->after('description');
            
            // Indexes for performance
            $table->index('unique_id');
            $table->index('slug');
            $table->index(['user_id', 'is_shareable']);
            $table->index('view_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex(['unique_id']);
            $table->dropIndex(['slug']);
            $table->dropIndex(['user_id', 'is_shareable']);
            $table->dropIndex(['view_count']);
            
            $table->dropColumn([
                'unique_id',
                'slug',
                'thumbnail_path',
                'compressed_path',
                'thumbnail_width',
                'thumbnail_height',
                'compressed_size',
                'is_shareable',
                'shared_at',
                'view_count',
                'alt_text',
                'description',
                'tags',
            ]);
        });
    }
};
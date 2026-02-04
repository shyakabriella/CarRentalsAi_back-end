<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imagegenerator', function (Blueprint $table) {
            $table->id();

            // Optional owners/links
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();

            // Where the image came from
            $table->enum('source', ['upload', 'generate'])->default('upload');

            // Stored file + public URLs
            $table->string('image_path', 1024)->nullable();  // e.g. storage path
            $table->string('image_url', 2048)->nullable();   // public URL/CDN
            $table->string('thumb_url', 2048)->nullable();   // optional thumbnail
            $table->boolean('is_primary')->default(false);   // mark main image for a vehicle

            // Generation metadata (for AI-generated images)
            $table->text('prompt')->nullable();
            $table->unsignedInteger('seed')->nullable();
            $table->string('style', 50)->nullable();         // e.g., 'studio', 'outdoor'
            $table->json('params')->nullable();              // arbitrary generator params

            // Simple job/status tracking
            $table->enum('status', ['queued', 'processing', 'succeeded', 'failed'])->default('succeeded');
            $table->text('error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Helpful indexes
            $table->index(['vehicle_id', 'is_primary']);
            $table->index(['user_id', 'status']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagegenerator');
    }
};

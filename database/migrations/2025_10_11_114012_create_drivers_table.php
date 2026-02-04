<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();

            // One user = one driver profile
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Profile photo (store path/url)
            $table->string('profile_image')->nullable(); // e.g. storage path or full URL

            // Personal details
            $table->string('gender')->nullable()->index();          // male|female|other (validate in FormRequest)
            $table->string('marital_status')->nullable()->index();  // single|married|divorced|widowed (validate)

            // License details
            $table->string('license_no')->nullable()->unique();
            $table->date('license_expiry')->nullable();
            $table->string('license_category')->nullable()->index(); // A|B|C|D|E (or Rwanda categories)
            $table->unsignedTinyInteger('experience_years')->default(0);

            // Location (Google Map)
            $table->decimal('current_lat', 10, 7)->nullable()->index(); // -90 to 90
            $table->decimal('current_lng', 10, 7)->nullable()->index(); // -180 to 180
            $table->string('current_address')->nullable();              // human-readable address
            $table->timestamp('location_updated_at')->nullable();

            // Status
            $table->string('status')->default('active')->index(); // active|inactive|suspended

            // Trust + availability
            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('is_available')->default(true)->index();

            // Ratings
            $table->decimal('rating_avg', 3, 2)->default(0); // 0.00 - 5.00
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('cancel_count')->default(0);

            // Extra flexible data (languages, preferences, documents meta, etc.)
            $table->json('profile')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Helpful compound indexes for matching/search
            $table->index(['status', 'is_available', 'is_verified']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('drivers');
    }
};
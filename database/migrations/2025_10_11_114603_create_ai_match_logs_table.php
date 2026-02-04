<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ai_match_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 6, 4)->default(0);
            $table->json('features')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();

            $table->index(['booking_id','score']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('ai_match_logs');
    }
};

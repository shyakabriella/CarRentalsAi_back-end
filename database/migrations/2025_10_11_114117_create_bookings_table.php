<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., SC-2025-000123
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('pickup_time');
            $table->dateTime('dropoff_time')->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status')->default('pending')->index();        // pending|confirmed|in_progress|completed|cancelled
            $table->string('payment_status')->default('unpaid')->index(); // unpaid|paid|partial|refunded
            $table->string('currency', 3)->default('RWF');
            $table->decimal('price_subtotal', 10, 2)->default(0);
            $table->decimal('price_driver_fee', 10, 2)->default(0);
            $table->decimal('price_taxes', 10, 2)->default(0);
            $table->decimal('price_total', 10, 2)->default(0);
            $table->json('pricing_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pickup_time', 'status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('bookings');
    }
};

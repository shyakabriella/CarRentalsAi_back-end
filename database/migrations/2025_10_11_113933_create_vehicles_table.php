<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->string('plate_no')->unique();
            $table->string('vin')->nullable()->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->unsignedInteger('odometer_km')->default(0);
            $table->decimal('base_daily_rate', 10, 2)->default(0);
            $table->decimal('base_hourly_rate', 10, 2)->default(0);
            $table->string('status')->default('available')->index(); 
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->json('media')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void {
        Schema::dropIfExists('vehicles');
    }
};

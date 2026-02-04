<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->index();           // irembo|momo|card
            $table->string('reference')->index();
            $table->string('status')->default('pending')->index(); // pending|succeeded|failed|refunded
            $table->string('currency', 3)->default('RWF');
            $table->decimal('amount', 10, 2);
            $table->dateTime('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payments');
    }
};

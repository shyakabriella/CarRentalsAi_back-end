<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('drivers', function (Blueprint $table) {
      $table->foreignId('vehicle_id')
        ->nullable()
        ->after('user_id')
        ->constrained('vehicles')
        ->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('drivers', function (Blueprint $table) {
      $table->dropConstrainedForeignId('vehicle_id');
    });
  }
};
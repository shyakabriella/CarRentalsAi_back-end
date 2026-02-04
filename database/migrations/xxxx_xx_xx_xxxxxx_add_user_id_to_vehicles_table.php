<?php


// database/migrations/xxxx_xx_xx_xxxxxx_add_user_id_to_vehicles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->index();
        });
    }
    public function down(): void {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

<?php

// database/migrations/xxxx_xx_xx_xxxxxx_tweak_unique_plate_no.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table) {
            // drop plain unique if it exists
            DB::statement('ALTER TABLE vehicles DROP INDEX vehicles_plate_no_unique');
            // add composite unique (plate_no, deleted_at)
            $table->unique(['plate_no','deleted_at'], 'vehicles_plate_no_deleted_at_unique');
        });
    }
    public function down(): void {
        Schema::table('vehicles', function (Blueprint $table) {
            DB::statement('ALTER TABLE vehicles DROP INDEX vehicles_plate_no_deleted_at_unique');
            $table->unique('plate_no');
        });
    }
};

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
        Schema::table('drivers', function (Blueprint $table) {

            // ✅ Add only if missing (safe)
            if (!Schema::hasColumn('drivers', 'current_lat')) {
                $table->decimal('current_lat', 10, 7)->nullable()->after('is_available');
            }

            if (!Schema::hasColumn('drivers', 'current_lng')) {
                $table->decimal('current_lng', 10, 7)->nullable()->after('current_lat');
            }

            if (!Schema::hasColumn('drivers', 'current_address')) {
                $table->string('current_address', 255)->nullable()->after('current_lng');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {

            // ✅ Drop only if exists
            if (Schema::hasColumn('drivers', 'current_address')) {
                $table->dropColumn('current_address');
            }

            if (Schema::hasColumn('drivers', 'current_lng')) {
                $table->dropColumn('current_lng');
            }

            if (Schema::hasColumn('drivers', 'current_lat')) {
                $table->dropColumn('current_lat');
            }
        });
    }
};
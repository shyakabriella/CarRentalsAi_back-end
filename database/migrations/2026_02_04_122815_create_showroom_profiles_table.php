<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showroom_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('owner_id')->unique(); // user id (owner)
            $table->string('name');                           // showroom name

            // Google map / address
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // uploads
            $table->string('logo_path')->nullable();                 // image
            $table->string('working_permission_pdf_path')->nullable(); // pdf

            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showroom_profiles');
    }
};
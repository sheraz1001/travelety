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
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata', 3)->unique();
            $table->string('city');
            $table->string('name');
            $table->string('country_code', 2);
            $table->string('country')->nullable();
            $table->string('city_slug')->index();
            $table->boolean('is_origin')->default(false);
            $table->boolean('is_destination')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};

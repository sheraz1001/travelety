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
        Schema::create('flight_routes', function (Blueprint $table) {
            $table->id();
            $table->string('origin_iata', 3)->index();
            $table->string('destination_iata', 3)->index();
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->json('faqs')->nullable();
            $table->json('fare_data')->nullable();
            $table->unsignedInteger('min_price')->nullable();
            $table->string('cheapest_month', 7)->nullable();
            $table->string('main_airline', 3)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_direct')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_indexable')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('fares_updated_at')->nullable();
            $table->timestamp('content_generated_at')->nullable();
            $table->timestamps();
            $table->unique(['origin_iata', 'destination_iata']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_routes');
    }
};

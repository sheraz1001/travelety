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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('city');
            $table->string('country');
            $table->string('iata', 3)->nullable();
            $table->string('tagline')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('theme_color', 20)->nullable();
            $table->longText('intro')->nullable();
            $table->longText('hotels_content')->nullable();
            $table->longText('guide_content')->nullable();
            $table->text('best_time')->nullable();
            $table->text('visa_uae')->nullable();
            $table->json('areas')->nullable();
            $table->json('things_to_do')->nullable();
            $table->json('weather')->nullable();
            $table->json('faqs')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_indexable')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('content_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};

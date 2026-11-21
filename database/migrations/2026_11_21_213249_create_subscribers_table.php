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
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('origin_iata', 3)->nullable();
            $table->string('destination_iata', 3)->nullable();
            $table->unsignedInteger('target_price')->nullable();
            $table->unsignedInteger('last_notified_price')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};

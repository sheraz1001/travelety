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
        Schema::create('esim_plans', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 40)->unique();
            $table->string('group_id', 120)->index();
            $table->string('group_type', 20)->index();
            $table->string('slug', 120)->index();
            $table->string('name', 160);
            $table->string('region', 80)->nullable()->index();
            $table->string('operator', 120)->nullable();
            $table->string('title', 255);
            $table->decimal('data_gb', 8, 2)->nullable();
            $table->string('data_label', 40);
            $table->unsignedSmallInteger('days');
            $table->decimal('price_usd', 10, 2)->index();
            $table->string('plan_type', 40)->default('data');
            $table->text('coverage')->nullable();
            $table->string('networks', 500)->nullable();
            $table->text('other_info')->nullable();
            $table->boolean('top_ups')->nullable();
            $table->string('link', 500);
            $table->string('image', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esim_plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->string('package_id', 120)->nullable()->index();
            $table->decimal('net_price_usd', 10, 2)->nullable();
            $table->boolean('partner_available')->default(false)->index();
            $table->timestamp('partner_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('esim_plans', function (Blueprint $table) {
            $table->dropColumn(['package_id', 'net_price_usd', 'partner_available', 'partner_synced_at']);
        });
    }
};

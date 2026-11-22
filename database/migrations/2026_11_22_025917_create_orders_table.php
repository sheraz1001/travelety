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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('token', 64)->unique();
            $table->string('type', 20)->index();
            $table->string('status', 30)->default('pending_payment')->index();
            $table->string('customer_name');
            $table->string('email')->index();
            $table->string('phone', 40)->nullable();
            $table->string('currency', 3)->default('AED');
            $table->decimal('amount', 12, 2);
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->string('cost_currency', 3)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_ref', 191)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('details')->nullable();
            $table->json('fulfilment')->nullable();
            $table->string('supplier_ref', 191)->nullable();
            $table->text('error')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

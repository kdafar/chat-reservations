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
        Schema::create('commerce_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('gateway_account_id')->nullable()->constrained('gateway_accounts')->nullOnDelete();
            $table->enum('method', ['cash', 'online'])->default('online');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->decimal('amount', 10, 3);
            $table->string('currency', 3)->default('KWD');
            $table->string('provider_payment_id')->nullable(); // PaymentId from the provider
            $table->string('transaction_id')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_payments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., MyFatoorah, Stripe, Tap, Cash
            $table->string('driver'); // e.g., myfatoorah, stripe, tap, cash
            $table->boolean('is_system')->default(false); // true for built-in system gateway definitions
            $table->timestamps();
            $table->unique(['driver', 'is_system']);
        });

        Schema::create('gateway_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateways')->cascadeOnDelete();
            $table->enum('owner_type', ['system', 'partner']);
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete(); // null when owner_type=system
            $table->string('display_name');
            $table->json('credentials')->nullable(); // API keys, tokens, profile IDs, etc.
            $table->string('currency', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // per partner or system
            $table->timestamps();
            $table->index(['gateway_id', 'owner_type', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_accounts');
        Schema::dropIfExists('gateways');
    }
};

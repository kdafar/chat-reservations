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
        Schema::create('commerce_payment_policies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_enabled')->default(true);
            $t->unsignedInteger('priority')->default(100); // lower = higher

            // Optional scoping; null = global
            $t->unsignedBigInteger('partner_id')->nullable()->index();
            $t->unsignedBigInteger('service_id')->nullable()->index();
            $t->unsignedBigInteger('branch_id')->nullable()->index();

            // Conditions + Action (JSON = flexible, backward-compatible)
            $t->json('conditions'); // { currency, order_type[], min_total, max_total, days_of_week[], time_between["09:00","23:59"] }
            $t->json('action');     // { gateway_account_id } OR { driver, owner_preference:["branch","partner","system"], allow_fallback:true }
            $t->timestamps();

            $t->index(['is_enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

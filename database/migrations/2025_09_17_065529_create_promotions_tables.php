<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->json('title');     // {"en":"...", "ar":"..."}
            $table->json('summary')->nullable();
            $table->string('type', 20)->index(); // item | cart | bundle
            $table->string('status', 20)->default('draft')->index(); // draft|active|archived
            $table->integer('priority')->default(100)->index();
            $table->string('stack_behavior', 24)->default('exclusive'); // stack|exclusive|exclusive_category
            $table->boolean('once_per_order')->default(false);
            $table->boolean('auto_apply')->default(true);
            $table->json('channels')->nullable(); // ["web","whatsapp"]

            $table->string('image_path')->nullable();

            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();

            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_user')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at', 'partner_id', 'branch_id', 'service_id', 'priority'], 'promotions_comp_idx');
        });

        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type', 40); // cart_min_subtotal | bxgy_same_item | has_items_set | order_type | time_window | in_category ...
            $table->json('payload'); // free-form JSON per type
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('promotion_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 40); // give_free_item | money_off_cart | free_delivery | bundle_price | percent_off_item ...
            $table->json('payload'); // free-form JSON per type
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_actions');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotions');
    }
};

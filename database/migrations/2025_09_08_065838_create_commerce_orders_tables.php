<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // customer
            $table->enum('type', ['delivery', 'pickup'])->default('delivery');
            $table->enum('status', [
                'draft', 'placed', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled', 'rejected',
            ])->default('placed');
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('snapshot_partner')->nullable();
            $table->json('snapshot_branch')->nullable();
            $table->decimal('items_total', 10, 3)->default(0);
            $table->decimal('delivery_fee', 10, 3)->default(0);
            $table->decimal('discount_total', 10, 3)->default(0);
            $table->decimal('tax_total', 10, 3)->default(0);
            $table->decimal('grand_total', 10, 3)->default(0);
            $table->string('currency', 3)->default('KWD');
            $table->string('notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'partner_id', 'branch_id', 'status']);
        });

        Schema::create('commerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 10, 3);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 3);
            $table->timestamps();
        });

        Schema::create('commerce_order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_order_item_id')->constrained('commerce_order_items')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('option_name');
            $table->decimal('price_delta', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_item_modifiers');
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
    }
};

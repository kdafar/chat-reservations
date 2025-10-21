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
        if (! Schema::hasTable('branch_block')) {
            Schema::create('branch_block', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('block_id')->constrained()->cascadeOnDelete();
                $table->decimal('delivery_fee', 8, 3)->nullable();
                $table->decimal('min_order_amount', 10, 3)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['branch_id', 'block_id']);
            });
        } else {
            Schema::table('branch_block', function (Blueprint $table) {
                if (! Schema::hasColumn('branch_block', 'delivery_fee')) {
                    $table->decimal('delivery_fee', 8, 3)->nullable()->after('block_id');
                }
                if (! Schema::hasColumn('branch_block', 'min_order_amount')) {
                    $table->decimal('min_order_amount', 10, 3)->nullable()->after('delivery_fee');
                }
                if (! Schema::hasColumn('branch_block', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('min_order_amount');
                }
                if (! Schema::hasColumn('branch_block', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        // Cautious down: only drop added columns if table pre-existed
        if (Schema::hasTable('branch_block')) {
            Schema::table('branch_block', function (Blueprint $table) {
                foreach (['delivery_fee', 'min_order_amount', 'is_active', 'created_at', 'updated_at'] as $col) {
                    if (Schema::hasColumn('branch_block', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

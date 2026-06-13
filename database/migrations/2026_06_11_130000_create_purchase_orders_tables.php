<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase-to-Pay: a clinic raises a purchase order to a vendor, receives the
 * goods into a branch's stock (partial allowed), and pays the vendor. Receiving
 * posts Dr Inventory / Cr Accounts Payable; paying posts Dr Accounts Payable /
 * Cr Cash/Bank. See App\Services\Clinic\PurchaseService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique();        // PO-YYYYMMDD-XXXX
            $table->unsignedBigInteger('vendor_id')->index();
            $table->unsignedBigInteger('branch_id')->index();         // receiving branch
            $table->string('status', 24)->default('draft')->index();  // draft|approved|partially_received|received|cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('total', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('clinic_item_id')->index();
            $table->decimal('qty_ordered', 14, 4);
            $table->decimal('qty_received', 14, 4)->default(0);
            $table->decimal('unit_cost', 14, 3);
            $table->decimal('line_total', 14, 3)->default(0);
            $table->timestamps();

            $table->foreign('clinic_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
        });

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique();        // GRN-YYYYMMDD-XXXX
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_order_line_id')->index();
            $table->unsignedBigInteger('clinic_item_id')->index();
            $table->decimal('qty_received', 14, 4);
            $table->decimal('unit_cost', 14, 3);
            $table->decimal('line_total', 14, 3)->default(0);
            $table->unsignedBigInteger('clinic_stock_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->cascadeOnDelete();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique();        // PAY-YYYYMMDD-XXXX
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->decimal('amount', 14, 3);
            $table->date('payment_date');
            $table->string('method', 32)->default('cash');
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->string('reference_no', 191)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('paid_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('payment_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_receipt_lines');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inter-branch stock transfers: a clinic moves stock from one branch (usually
 * its hub) to another. Dispatching a transfer consumes from the source branch
 * and restocks the destination branch (see StockTransferService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable()->index(); // clinic (tenancy)
            $table->unsignedBigInteger('from_branch_id')->index();         // source (e.g. hub)
            $table->unsignedBigInteger('to_branch_id')->index();           // destination
            $table->string('status', 16)->default('pending')->index();     // pending | dispatched | cancelled
            $table->unsignedBigInteger('visit_id')->nullable()->index();   // set when sourced for a visit need
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('dispatched_by_user_id')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            $table->foreign('from_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('to_branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('visit_id')->references('id')->on('visits')->nullOnDelete();
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('clinic_item_id')->index();
            $table->decimal('qty_base', 14, 4);
            $table->timestamps();

            $table->foreign('clinic_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};

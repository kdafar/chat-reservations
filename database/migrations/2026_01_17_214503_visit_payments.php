<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            Schema::create('visit_payments', function (Blueprint $table) {
                $table->id();

                $table->foreignId('visit_id')
                    ->constrained('visits')
                    ->cascadeOnDelete();

                $table->decimal('amount', 12, 3);

                // e.g. cash, knet, card, transfer, link, insurance
                $table->string('method', 50)->index();

                // paid, pending, refunded, void
                $table->string('status', 20)->default('paid')->index();

                // gateway txn id / receipt no
                $table->string('reference_no', 191)->nullable();

                $table->foreignId('collected_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->dateTime('paid_at')->nullable()->index();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['visit_id', 'method']);
                $table->index(['method', 'paid_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visit_payments')) {
            Schema::drop('visit_payments');
        }
    }
};

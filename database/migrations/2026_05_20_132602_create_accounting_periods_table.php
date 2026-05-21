<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();

            // Human code, typically "2026-05" (year-month). Unique.
            $table->string('code', 16)->unique();

            $table->date('start_date');
            $table->date('end_date');

            // Open = entries can be posted. Closed = locked, no edits/posts allowed.
            $table->enum('status', ['open', 'closed'])->default('open');

            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_stock_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('branch_id')->index();

            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();

            $table->string('status', 32)->default('pending')->index(); // pending/fulfilled/cancelled

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('fulfilled_by_user_id')->nullable()->index();
            $table->dateTime('fulfilled_at')->nullable();

            $table->timestamps();

            // FKs (use restrict to avoid cascading surprises in prod)
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();

            // Users table name is usually users
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('fulfilled_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_stock_requests');
    }
};

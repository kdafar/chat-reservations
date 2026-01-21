<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_charges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->string('label', 255);
            $table->decimal('qty', 12, 3)->default(1);

            // Snapshot pricing
            $table->decimal('unit_price_snapshot', 12, 3)->default(0);
            $table->decimal('line_total', 12, 3)->default(0);

            $table->unsignedBigInteger('added_by_user_id')->nullable()->index();

            $table->timestamps();

            $table->foreign('visit_id')->references('id')->on('visits')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_charges');
    }
};

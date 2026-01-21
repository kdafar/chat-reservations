<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_packages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('clinic_package_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->decimal('qty', 12, 3)->default(1);

            // Snapshot price at time of adding to visit
            $table->decimal('unit_price_snapshot', 12, 3)->default(0);
            $table->decimal('line_total', 12, 3)->default(0);

            $table->unsignedBigInteger('added_by_user_id')->nullable()->index();

            $table->timestamps();

            $table->foreign('visit_id')->references('id')->on('visits')->cascadeOnDelete();
            $table->foreign('clinic_package_id')->references('id')->on('clinic_packages')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['visit_id', 'clinic_package_id'], 'vp_unique_visit_pkg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_packages');
    }
};

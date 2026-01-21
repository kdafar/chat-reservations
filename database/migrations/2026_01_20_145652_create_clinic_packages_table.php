<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_packages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->json('name'); // {en, ar}
            $table->boolean('is_active')->default(true)->index();

            // Optional: default price snapshot source when adding to visit
            $table->decimal('default_price', 12, 3)->default(0);

            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_packages');
    }
};

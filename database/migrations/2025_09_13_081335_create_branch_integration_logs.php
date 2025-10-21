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
        Schema::create('branch_integration_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_integration_id')->constrained()->cascadeOnDelete();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->string('status', 20)->default('running'); // running|success|failed
            $t->unsignedInteger('categories')->default(0);
            $t->unsignedInteger('items')->default(0);
            $t->text('message')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['branch_integration_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_integration_logs');
    }
};

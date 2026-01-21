<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');   // 0=Sun .. 6=Sat (or use 1..7 if you prefer)
            $table->time('open_at');
            $table->time('close_at');
            $table->json('capacity_map')->nullable();     // {"2":8,"4":6,"6":3}
            $table->unsignedSmallInteger('lead_time_minutes')->default(60);
            $table->timestamps();

            $table->unique(['branch_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_availability_rules');
    }
};

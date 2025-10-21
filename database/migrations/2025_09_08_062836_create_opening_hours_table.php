<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=Sun .. 6=Sat (or your preference)
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('is_closed')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'weekday', 'opens_at', 'closes_at'], 'branch_weekday_range_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};

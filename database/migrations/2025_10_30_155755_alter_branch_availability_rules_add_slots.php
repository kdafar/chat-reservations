<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_availability_rules', function (Blueprint $t) {
            $t->boolean('is_open')->default(true)->after('day_of_week');
            $t->unsignedSmallInteger('slot_length_minutes')->default(90)->after('close_at');
            $t->unsignedSmallInteger('slot_step_minutes')->default(30)->after('slot_length_minutes');
            $t->unsignedTinyInteger('max_party_size')->default(6)->after('slot_step_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('branch_availability_rules', function (Blueprint $t) {
            $t->dropColumn(['is_open', 'slot_length_minutes', 'slot_step_minutes', 'max_party_size']);
        });
    }
};

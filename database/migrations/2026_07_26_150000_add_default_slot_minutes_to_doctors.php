<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long one appointment with this doctor takes.
 *
 * Nullable on purpose: null means "use the branch's appointment length", which
 * is how every existing doctor keeps behaving until someone sets a value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_slot_minutes')->nullable()->after('consultation_fee');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('default_slot_minutes');
        });
    }
};

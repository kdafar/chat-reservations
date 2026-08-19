<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the patient asked for when they booked.
 *
 * A visitor who books from the offers page has already chosen a package. That
 * choice used to die at the "Book" button — reception met the patient with no
 * idea which offer brought them in. Carrying the id on the booking lets the
 * queue show it before check-in, and lets reception add the right package to
 * the visit without asking the patient to repeat themselves.
 *
 * Nullable and null-on-delete: it is a record of intent, not a commitment. The
 * real money still lives on visit_packages once it is actually applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('requested_package_id')
                ->nullable()
                ->after('table_id')
                ->constrained('clinic_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['requested_package_id']);
            $table->dropColumn('requested_package_id');
        });
    }
};

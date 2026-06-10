<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone numbers get reassigned to different people in real life. Reception can
 * now split a reassigned number into a NEW patient (same phone, same clinic),
 * so the (partner_id, phone) UNIQUE index has to go. We keep a plain composite
 * index so phone lookups stay fast — duplicates are resolved by "most recent
 * wins" in BookingService::resolveOrCreatePatientId().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add the plain replacement index FIRST: the (partner_id, phone) unique
        // also serves as the covering index for the partner_id foreign key, so
        // it can't be dropped until another index covers partner_id.
        Schema::table('patients', function (Blueprint $table) {
            $table->index(['partner_id', 'phone'], 'patients_partner_id_phone_index');
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_partner_id_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_partner_id_phone_index');
            $table->unique(['partner_id', 'phone'], 'patients_partner_id_phone_unique');
        });
    }
};

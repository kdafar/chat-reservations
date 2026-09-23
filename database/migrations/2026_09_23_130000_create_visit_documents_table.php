<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice and receipt numbers for printed visit papers.
 *
 * One invoice number per visit (a reprint or a one-section copy keeps it),
 * one receipt number per payment. Numbers run per year with no gaps
 * (INV-2026-000001, RC-2026-000001) and are stored with a snapshot of the
 * amounts at first print, so a paper handed to a patient can be traced back.
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_documents')) {
            return;
        }
        Schema::create('visit_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->index();
            $t->foreignId('payment_id')->nullable()->unique();
            $t->string('kind', 16);                 // invoice | receipt
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('seq');
            $t->string('number', 32)->unique();
            $t->json('snapshot')->nullable();       // totals at first print
            $t->foreignId('first_printed_by_user_id')->nullable();
            $t->timestamp('first_printed_at');
            $t->timestamp('last_printed_at');
            $t->unsignedInteger('print_count')->default(1);
            $t->timestamps();
            $t->unique(['kind', 'year', 'seq']);
            $t->index(['visit_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_documents');
    }
};

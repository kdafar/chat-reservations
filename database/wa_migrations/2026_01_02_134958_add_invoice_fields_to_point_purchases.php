<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('point_purchases', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->index();
            }
            if (! Schema::hasColumn('point_purchases', 'invoice_pdf_path')) {
                $table->string('invoice_pdf_path')->nullable();
            }
            if (! Schema::hasColumn('point_purchases', 'invoice_sent_at')) {
                $table->timestamp('invoice_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_pdf_path', 'invoice_sent_at']);
        });
    }
};

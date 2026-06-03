<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_items', function (Blueprint $table) {
            // Per-line discount in KWD (decimal:3). Subtracted from line_price_total when computing visit.items_price_total.
            // Independent of visit.discount_total which is a separate visit-level "goodwill" override.
            $table->decimal('discount_amount', 12, 3)->default(0)->after('line_price_total');
        });
    }

    public function down(): void
    {
        Schema::table('visit_items', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};

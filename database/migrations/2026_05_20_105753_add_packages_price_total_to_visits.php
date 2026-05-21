<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'packages_price_total')) {
                $table->decimal('packages_price_total', 12, 3)
                    ->default(0)
                    ->after('items_price_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'packages_price_total')) {
                $table->dropColumn('packages_price_total');
            }
        });
    }
};

<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('gateways', 'name')) {
            $gateways = Gateway::all();
            foreach ($gateways as $gateway) {
                // Assuming 'en' as the default locale for existing names.
                $gateway->update(['name' => ['en' => $gateway->getRawOriginal('name')]]);
            }
        }

        Schema::table('gateways', function (Blueprint $table) {
            $table->json('name')->change();
            // Add a translatable description field.
            $table->json('description')->nullable()->after('driver');

            // Add a field to store the path to the logo.
            $table->string('logo_path')->nullable()->after('description');

            // Add an active status toggle.
            $table->boolean('is_active')->default(true)->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('gateways', 'name')) {
            $gateways = Gateway::all();
            foreach ($gateways as $gateway) {
                // Extract the 'en' value, or the first value found.
                $nameArray = $gateway->getTranslations('name');
                $firstValue = reset($nameArray);
                DB::table('gateways')->where('id', $gateway->id)->update(['name' => $firstValue]);
            }
        }
        Schema::table('gateways', function (Blueprint $table) {
            $table->dropColumn(['description', 'logo_path', 'is_active']);
            $table->string('name')->change();
        });
    }
};

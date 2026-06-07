<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // JSON column for Spatie translatable
            if (! Schema::hasColumn('restaurants', 'badge_label')) {
                $table->json('badge_label')->nullable()->after('badge_emoji');
            }
        });

        // backfill from old columns if present
        if (Schema::hasColumn('restaurants', 'badge_label_en') || Schema::hasColumn('restaurants', 'badge_label_ar')) {
            // build {"en": "...", "ar": "..."} JSON
            DB::table('restaurants')->select('id', 'badge_label_en', 'badge_label_ar')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $map = [];
                    if (! is_null($r->badge_label_en) && $r->badge_label_en !== '') {
                        $map['en'] = $r->badge_label_en;
                    }
                    if (! is_null($r->badge_label_ar) && $r->badge_label_ar !== '') {
                        $map['ar'] = $r->badge_label_ar;
                    }
                    if ($map) {
                        DB::table('restaurants')->where('id', $r->id)
                            ->update(['badge_label' => json_encode($map, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });

            // drop old columns
            Schema::table('restaurants', function (Blueprint $table) {
                if (Schema::hasColumn('restaurants', 'badge_label_en')) {
                    $table->dropColumn('badge_label_en');
                }
                if (Schema::hasColumn('restaurants', 'badge_label_ar')) {
                    $table->dropColumn('badge_label_ar');
                }
            });
        }
    }

    public function down(): void
    {
        // recreate old columns (nullable) and attempt naive restore
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'badge_label_en')) {
                $table->string('badge_label_en', 32)->nullable()->after('badge_emoji');
            }
            if (! Schema::hasColumn('restaurants', 'badge_label_ar')) {
                $table->string('badge_label_ar', 32)->nullable()->after('badge_label_en');
            }
        });

        // best-effort split back
        DB::table('restaurants')->select('id', 'badge_label')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                if (! $r->badge_label) {
                    continue;
                }
                $map = json_decode($r->badge_label, true) ?: [];
                DB::table('restaurants')->where('id', $r->id)->update([
                    'badge_label_en' => $map['en'] ?? null,
                    'badge_label_ar' => $map['ar'] ?? null,
                ]);
            }
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('badge_label');
        });
    }
};

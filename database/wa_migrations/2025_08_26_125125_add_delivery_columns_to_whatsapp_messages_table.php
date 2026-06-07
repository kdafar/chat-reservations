<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'meta_message_id')) {
                if (! Schema::hasColumn('whatsapp_messages', 'meta_message_id')) {
                    $table->string('meta_message_id')->nullable()->index();
                }
            }

            // Use string for cross-DB compatibility (enum can be added as a check later if you want)
            if (! Schema::hasColumn('whatsapp_messages', 'delivery_status')) {
                $table->string('delivery_status', 20)->nullable()->index(); // queued|sent|delivered|read|failed
            }

            if (! Schema::hasColumn('whatsapp_messages', 'error_code')) {
                if (! Schema::hasColumn('whatsapp_messages', 'error_code')) {
                    $table->integer('error_code')->nullable();
                }
            }

            if (! Schema::hasColumn('whatsapp_messages', 'error_title')) {
                if (! Schema::hasColumn('whatsapp_messages', 'error_title')) {
                    $table->string('error_title')->nullable();
                }
            }

            if (! Schema::hasColumn('whatsapp_messages', 'error_details')) {
                // SQLite doesn't have native JSON type; fall back to TEXT
                if (DB::getDriverName() === 'sqlite') {
                    if (! Schema::hasColumn('whatsapp_messages', 'error_details')) {
                        $table->text('error_details')->nullable();
                    }
                } else {
                    if (! Schema::hasColumn('whatsapp_messages', 'error_details')) {
                        $table->json('error_details')->nullable();
                    }
                }
            }
        });

        // Optional: add a CHECK constraint for delivery_status on MySQL/Postgres (skip SQLite)
        // if (DB::getDriverName() !== 'sqlite') {
        //     DB::statement("ALTER TABLE whatsapp_messages
        //         ADD CONSTRAINT chk_whatsapp_messages_delivery_status
        //         CHECK (delivery_status IN ('queued','sent','delivered','read','failed'))");
        // }
    }

    public function down(): void
    {
        // Drop constraint first if you added it above
        // if (DB::getDriverName() !== 'sqlite') {
        //     DB::statement("ALTER TABLE whatsapp_messages DROP CONSTRAINT IF EXISTS chk_whatsapp_messages_delivery_status");
        //     // MySQL <8 syntax uses DROP CHECK chk_whatsapp_messages_delivery_status;
        // }

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $cols = ['meta_message_id', 'delivery_status', 'error_code', 'error_title', 'error_details'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('whatsapp_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

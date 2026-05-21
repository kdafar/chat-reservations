<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename permissions that were originally seeded with typo'd resource keys
     * ("clinic_item _stock", "bulk_invite_Campaign", "WAMessage", "WAMessage_log",
     * "WACommand") so that the policy classes and clinic-filament-policies config
     * can use clean snake_case keys without breaking existing role assignments.
     *
     * role_has_permissions uses permission_id FKs so role grants survive the rename.
     */
    private const MAP = [
        'clinic_item _stock' => 'clinic_item_stocks',
        'bulk_invite_Campaign' => 'bulk_invite_campaigns',
        'WAMessage_log' => 'wa_message_logs',
        'WAMessage' => 'wa_messages',
        'WACommand' => 'wa_commands',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::MAP as $oldSuffix => $newSuffix) {
            // Match permission rows whose `name` ends with "_{oldSuffix}".
            // The actions (view_any_, view_, create_, ...) form the prefix.
            DB::table('permissions')
                ->where('name', 'LIKE', '%\_'.str_replace('_', '\\_', $oldSuffix))
                ->get(['id', 'name'])
                ->each(function ($row) use ($oldSuffix, $newSuffix) {
                    $newName = preg_replace('/_'.preg_quote($oldSuffix, '/').'$/', '_'.$newSuffix, $row->name);
                    if ($newName !== $row->name) {
                        DB::table('permissions')->where('id', $row->id)->update(['name' => $newName]);
                    }
                });
        }

        // Spatie caches permissions; clear so the new names take effect immediately.
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (self::MAP as $oldSuffix => $newSuffix) {
            DB::table('permissions')
                ->where('name', 'LIKE', '%\_'.str_replace('_', '\\_', $newSuffix))
                ->get(['id', 'name'])
                ->each(function ($row) use ($oldSuffix, $newSuffix) {
                    $orig = preg_replace('/_'.preg_quote($newSuffix, '/').'$/', '_'.$oldSuffix, $row->name);
                    if ($orig !== $row->name) {
                        DB::table('permissions')->where('id', $row->id)->update(['name' => $orig]);
                    }
                });
        }

        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};

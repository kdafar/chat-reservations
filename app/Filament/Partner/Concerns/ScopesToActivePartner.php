<?php

namespace App\Filament\Partner\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopesToActivePartner
{
    public static function getEloquentQuery(): Builder
    {
        $partnerId = (int) session('active_partner_id');
        $modelClass = static::getModel();
        $model = new $modelClass;
        $table = $model->getTable();
        $q = $modelClass::query();

        // 1) Direct partner_id
        if (Schema::hasColumn($table, 'partner_id')) {
            return $q->where("$table.partner_id", $partnerId);
        }

        // 2) Direct branch_id (menus, menu_items, modifier_groups already have this)
        if (Schema::hasColumn($table, 'branch_id')) {
            return $q->whereIn("$table.branch_id", function ($sub) use ($partnerId) {
                $sub->select('id')->from('branches')->where('partner_id', $partnerId);
            });
        }

        // 3) menu_sections -> menus -> branches -> partner
        if ($table === 'menu_sections') {
            return $q->whereIn('menu_id', function ($sub) use ($partnerId) {
                $sub->select('id')->from('menus')->whereIn('branch_id', function ($s) use ($partnerId) {
                    $s->select('id')->from('branches')->where('partner_id', $partnerId);
                });
            });
        }

        // Fallback: return unscoped (shouldn't happen for partner panel models)
        return $q;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // Only set partner_id if the model actually has that column.
        if (array_key_exists('partner_id', $data)) {
            $data['partner_id'] = (int) session('active_partner_id');
        }

        return $data;
    }
}

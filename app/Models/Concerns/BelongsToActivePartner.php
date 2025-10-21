<?php

namespace App\Filament\Partner\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesToActivePartner
{
    protected static function scopeToActivePartner(Builder $q, string $branchColumn = 'branch_id'): Builder
    {
        $partnerId = (int) session('active_partner_id');

        return $q->whereIn($branchColumn, function ($sub) use ($partnerId) {
            $sub->select('id')->from('branches')->where('partner_id', $partnerId);
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReservationTerm extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = [
        'branch_id', 'is_active', 'terms_required',
        'label_en', 'label_ar', 'text_en', 'text_ar',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public static function forBranch(?int $branchId): ?self
    {
        // Prefer branch-specific active, else global active
        return static::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->active()
            ->first()
            ?: static::query()->whereNull('branch_id')->active()->first();
    }

    public function label(string $locale): string
    {
        return $locale === 'ar' ? ($this->label_ar ?: $this->label_en) : ($this->label_en ?: $this->label_ar);
    }

    public function text(string $locale): string
    {
        return $locale === 'ar' ? ($this->text_ar ?: $this->text_en ?: '') : ($this->text_en ?: $this->text_ar ?: '');
    }
}

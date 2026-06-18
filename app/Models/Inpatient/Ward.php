<?php

namespace App\Models\Inpatient;

use App\Models\Concerns\LogsClinicActivity;

use App\Models\Branch;
use App\Models\Concerns\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    use LogsClinicActivity;

    use BelongsToBranchScope;

    protected $guarded = [];

    public const TYPE_GENERAL = 'general';
    public const TYPE_ICU = 'icu';
    public const TYPE_PEDIATRIC = 'pediatric';
    public const TYPE_MATERNITY = 'maternity';
    public const TYPE_ISOLATION = 'isolation';
    public const TYPE_VIP = 'vip';

    public const GENDER_ANY = 'any';
    public const GENDER_MALE = 'male';
    public const GENDER_FEMALE = 'female';

    protected $casts = [
        'daily_rate' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function availableBeds(): HasMany
    {
        return $this->beds()->where('status', Bed::STATUS_AVAILABLE)->where('is_active', true);
    }
}

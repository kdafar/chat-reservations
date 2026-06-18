<?php

namespace App\Models\Insurance;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurer extends Model
{
    use LogsClinicActivity;

    use SoftDeletes;

    protected $table = 'insurers';

    protected $fillable = [
        'name',
        'name_ar',
        'code',
        'tax_id',
        'contact_email',
        'contact_phone',
        'address',
        'payment_terms_days',
        'is_active',
        'notes',
        'meta',
        'ar_account_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'bool',
        'ar_account_id' => 'integer',
    ];

    /** This insurer's accounts-receivable account — see ChartOfAccounts. */
    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'ar_account_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class, 'insurer_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientInsurancePolicy::class, 'insurer_id');
    }
}

<?php

namespace App\Models\Insurance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurer extends Model
{
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
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'bool',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(InsurancePlan::class, 'insurer_id');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PatientInsurancePolicy::class, 'insurer_id');
    }
}

<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = ['msisdn', 'name', 'locale'];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group');
    }

    public function contactGroups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group');
    }

    public function engagementStat(): HasOne
    {
        return $this->hasOne(ContactEngagementStat::class);
    }
}

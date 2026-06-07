<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactGroup extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'group_type',
        'filters_json',
        'auto_refresh',
        'last_synced_at',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'auto_refresh' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_contact_group')
            ->withTimestamps();
    }

    public function isDynamic(): bool
    {
        return $this->group_type === 'dynamic';
    }
}

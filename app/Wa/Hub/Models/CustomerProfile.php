<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'whatsapp_session_id',
        'full_name',
        'phone_number',
        'default_delivery_address',
        'default_apartment_number',
        'notes',
        'addresses',
    ];

    protected $casts = [
        'addresses' => 'array',
    ];

    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    public function getAddress(string $slug): ?array
    {
        return \App\Support\AddressBook::get($this->addresses ?? [], $slug);
    }

    public function saveAddress(array $addr): void
    {
        $addresses = $this->addresses ?? [];
        \App\Support\AddressBook::upsert($addresses, $addr);
        $this->addresses = $addresses;
        $this->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappFlowState extends Model
{
    protected $fillable = [
        'flow_token',   // uuid from your sender()
        'msisdn',       // session->phone
        'screen',       // current logical screen: APPOINTMENT/DETAILS/SUMMARY/CONFIRMATION
        'data',         // JSON blob: {branch_id, party_size, res_date, res_time, slot_key, name, phone, email, notes, ...}
        'expires_at',   // optional TTL
    ];

    protected $casts = [
        'data' => 'array',
        'expires_at' => 'datetime',
    ];

    // helpers
    public function mergeData(array $patch): self
    {
        $this->data = array_replace((array) $this->data, $patch);

        return $this;
    }

    /* scopes */
    public function scopeByToken($q, string $token)
    {
        return $q->where('flow_token', $token);
    }

    public function scopeAlive($q)
    {
        return $q->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}

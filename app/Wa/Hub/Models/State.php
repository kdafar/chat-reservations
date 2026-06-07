<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $connection = 'wa';
    protected $table = 'state';

    protected $fillable = ['state_name', 'state_name_ar'];

    public function cities()
    {
        return $this->hasMany(City::class, 'state_id', 'id')->orderBy('city_name_ar');
    }

    public function city()
    {
        return $this->hasMany(City::class, 'state_id', 'id')->orderBy('city_name_ar');
    }

    public function whatsappSessions()
    {
        return $this->hasMany(WhatsappSession::class, 'delivery_state_id');
    }
}

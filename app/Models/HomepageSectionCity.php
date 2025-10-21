<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSectionCity extends Model
{
    protected $table = 'homepage_section_city';

    protected $fillable = ['homepage_section_id', 'city_id', 'sort_order'];

    public function section()
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}

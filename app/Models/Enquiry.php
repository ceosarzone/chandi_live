<?php

namespace App\Models;

class Enquiry extends Record
{
    protected $table = 'enquiries';

    protected $casts = [];

    public function dealer()
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_profile_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

namespace App\Models;

class DealerDocument extends Record
{
    protected $table = 'dealer_documents';

    protected $casts = [];

    public function dealer()
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_profile_id');
    }
}

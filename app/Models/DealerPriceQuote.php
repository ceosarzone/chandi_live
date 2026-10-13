<?php

namespace App\Models;

class DealerPriceQuote extends Record
{
    protected $table = 'dealer_price_quotes';

    protected $casts = ['observed_at' => 'datetime', 'expires_at' => 'datetime'];
}

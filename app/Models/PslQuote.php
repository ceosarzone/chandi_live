<?php

namespace App\Models;

class PslQuote extends Record
{
    protected $casts = ['observed_at' => 'immutable_datetime', 'fetched_at' => 'immutable_datetime'];
}

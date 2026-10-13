<?php

namespace App\Models;

class Payment extends Record
{
    protected $table = 'payments';

    protected $casts = ['confirmed_at' => 'datetime'];
}

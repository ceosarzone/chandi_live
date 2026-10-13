<?php

namespace App\Models;

class Role extends Record
{
    protected $table = 'roles';

    protected $casts = ['permissions' => 'array'];
}

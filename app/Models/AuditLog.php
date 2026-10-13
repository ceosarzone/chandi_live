<?php

namespace App\Models;

class AuditLog extends Record
{
    protected $table = 'audit_logs';

    protected $casts = ['changes' => 'array'];
}

<?php

namespace App\Services;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $action, $subject = null, array $changes = []): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->id, 'changes' => array_diff_key($changes, array_flip(['password', 'totp_secret', 'api_key', 'path', 'email', 'phone', 'message']))]);
    }
}

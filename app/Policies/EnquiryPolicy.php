<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    public function update(User $u, Enquiry $e): bool
    {
        return $u->canManage('enquiries') || ($u->active && $e->dealer?->user_id === $u->id && $e->dealer?->status === 'approved');
    }
}

<?php

namespace App\Policies;

use App\Models\DealerProfile;
use App\Models\User;

class DealerProfilePolicy
{
    public function update(User $u, DealerProfile $d): bool
    {
        return $u->active && $u->id === $d->user_id && $d->status !== 'suspended';
    }

    public function review(User $u, DealerProfile $d): bool
    {
        return $u->canManage('dealers');
    }
}

<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function update(User $u, Product $p): bool
    {
        return $u->active && $p->dealer->user_id === $u->id && $p->dealer->status === 'approved';
    }

    public function delete(User $u, Product $p): bool
    {
        return $this->update($u, $p);
    }
}

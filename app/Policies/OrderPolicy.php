<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Customers may only see their own orders. Admin access is not granted here;
 * it will be added deliberately with the admin panel (Phase 9).
 * Usage: $this->authorize('view', $order) / Gate::allows('view', $order).
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->getKey();
    }
}

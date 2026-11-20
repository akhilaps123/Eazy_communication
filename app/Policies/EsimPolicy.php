<?php

namespace App\Policies;

use App\Models\Esim;
use App\Models\User;

/**
 * Customers may only see their own eSIMs (and therefore their QR codes and
 * activation details).
 */
class EsimPolicy
{
    public function view(User $user, Esim $esim): bool
    {
        return $esim->isOwnedBy($user);
    }

    /**
     * Phase 9D: QR code and activation details (SM-DP+ address, matching ID).
     * The owner, or an administrator (admin routes additionally check that the
     * eSIM belongs to the order in the URL).
     */
    public function viewActivation(User $user, Esim $esim): bool
    {
        return $user->isAdmin() || $esim->isOwnedBy($user);
    }

    /** Phase 9D: re-creating a QR code from the stored details is an admin action. */
    public function regenerateQr(User $user, Esim $esim): bool
    {
        return $user->isAdmin();
    }
}

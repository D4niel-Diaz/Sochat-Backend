<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminPolicy
{
    use HandlesAuthorization;

    public function viewMetrics(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function manageChats(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function manageReports(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function banGuests(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function unbanGuests(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function resolveReports(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }

    public function viewFlaggedMessages(Admin $admin): bool
    {
        return $admin->isAdmin() || $admin->isSuperAdmin();
    }
}

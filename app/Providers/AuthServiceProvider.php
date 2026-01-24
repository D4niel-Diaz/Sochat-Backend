<?php

namespace App\Providers;

use App\Models\Admin;
use App\Policies\AdminPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Admin::class => AdminPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin.view-metrics', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.manage-chats', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.manage-reports', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.ban-guests', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.unban-guests', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.resolve-reports', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
        Gate::define('admin.view-flagged', fn ($admin) => $admin->isAdmin() || $admin->isSuperAdmin());
    }
}

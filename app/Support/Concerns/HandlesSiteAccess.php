<?php

namespace App\Support\Concerns;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Shared helpers to determine which sites a user can interact with.
 */
trait HandlesSiteAccess
{
    protected function isSuperAdmin(User $user): bool
    {
        return $user->role?->slug === 'super_admin';
    }

    /**
     * Build a base query for sites visible to the given user.
     */
    protected function accessibleSitesQuery(User $user): Builder
    {
        return Site::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('tenant_id')
                ->orWhere('tenant_id', $user->tenant_id))
            ->when(! $this->isSuperAdmin($user), function (Builder $query) use ($user) {
                if ($user->site_id) {
                    $query->whereKey($user->site_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    /**
     * Ensure the requested site belongs to the caller's accessible scope.
     */
    protected function assertSiteAccessible(User $user, string $siteId): void
    {
        $canAccess = $this->accessibleSitesQuery($user)
            ->whereKey($siteId)
            ->exists();

        if (! $canAccess) {
            throw ValidationException::withMessages([
                'siteId' => ['You are not authorized to interact with the selected site.'],
            ]);
        }
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionMatrix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $moduleKey, ?string $action = null): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        if ($user->role?->slug === 'super_admin') {
            return $next($request);
        }

        $modules = PermissionMatrix::modules();

        if (! isset($modules[$moduleKey])) {
            abort(500, sprintf('Unknown permission module [%s].', $moduleKey));
        }

        $resolvedAction = $this->resolveAction($request->method(), $action);
        $requiredSlugs = $modules[$moduleKey]['permissions'][$resolvedAction] ?? [];

        if (empty($requiredSlugs)) {
            abort(403, 'You do not have access to this action.');
        }

        $hasAll = collect($requiredSlugs)->every(static fn (string $permission) => $user->hasPermission($permission));

        if (! $hasAll) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }

    private function resolveAction(string $method, ?string $explicitAction): string
    {
        $normalized = $explicitAction ? strtolower($explicitAction) : null;

        if (in_array($normalized, ['view', 'write', 'delete'], true)) {
            return $normalized;
        }

        $upperMethod = strtoupper($method);

        if (in_array($upperMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return 'view';
        }

        if ($upperMethod === 'DELETE') {
            return 'delete';
        }

        return 'write';
    }
}

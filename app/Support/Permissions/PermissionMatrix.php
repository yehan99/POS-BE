<?php

namespace App\Support\Permissions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PermissionMatrix
{
    public static function modules(): array
    {
        $modules = config('permission-modules.modules', []);

        return collect($modules)
            ->map(function (array $module, string $key) {
                $permissions = $module['permissions'] ?? [];

                return [
                    'key' => $key,
                    'label' => $module['label'] ?? self::formatLabel($key),
                    'description' => $module['description'] ?? null,
                    'icon' => $module['icon'] ?? null,
                    'permissions' => [
                        'view' => $permissions['view'] ?? [],
                        'write' => $permissions['write'] ?? [],
                        'delete' => $permissions['delete'] ?? [],
                    ],
                ];
            })
            ->keyBy('key')
            ->all();
    }

    public static function modulesForResponse(): array
    {
        return array_values(array_map(function (array $module) {
            return array_merge($module, [
                'capabilities' => [
                    'view' => !empty($module['permissions']['view']),
                    'write' => !empty($module['permissions']['write']),
                    'delete' => !empty($module['permissions']['delete']),
                ],
            ]);
        }, self::modules()));
    }

    public static function summarize(Collection|array $permissionSlugs): array
    {
        $slugs = $permissionSlugs instanceof Collection
            ? $permissionSlugs
            : collect($permissionSlugs);

        $modules = self::modules();

        return array_values(array_map(function (array $module) use ($slugs) {
            $permissions = $module['permissions'];

            return array_merge($module, [
                'matrix' => [
                    'view' => self::containsAll($slugs, $permissions['view']),
                    'write' => self::containsAll($slugs, $permissions['write']),
                    'delete' => self::containsAll($slugs, $permissions['delete']),
                ],
            ]);
        }, $modules));
    }

    public static function slugsFromMatrix(array $matrix): array
    {
        $modules = self::modules();
        $granted = collect();

        foreach ($matrix as $entry) {
            $key = Arr::get($entry, 'key');
            if (!$key || !isset($modules[$key])) {
                continue;
            }

            $actions = [
                'view' => (bool) Arr::get($entry, 'view'),
                'write' => (bool) Arr::get($entry, 'write'),
                'delete' => (bool) Arr::get($entry, 'delete'),
            ];

            if ($actions['delete']) {
                $actions['write'] = true;
                $actions['view'] = true;
            }

            if ($actions['write']) {
                $actions['view'] = true;
            }

            foreach ($actions as $action => $enabled) {
                if (!$enabled) {
                    continue;
                }

                $granted = $granted->merge($modules[$key]['permissions'][$action] ?? []);
            }
        }

        return $granted->unique()->values()->all();
    }

    private static function containsAll(Collection $owned, array $required): bool
    {
        if (empty($required)) {
            return false;
        }

        return collect($required)->every(fn (string $slug) => $owned->contains($slug));
    }

    private static function formatLabel(string $key): string
    {
        return collect(explode('_', $key))
            ->map(fn ($segment) => ucfirst($segment))
            ->implode(' ');
    }
}

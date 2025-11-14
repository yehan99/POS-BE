<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $search = $this->normalizeSearchTerm($request->query('search'));
        $status = $this->normalizeStatus($request->query('status'));
        $perPage = $this->sanitizePerPage((int) $request->query('perPage', 25));
        $page = max((int) $request->query('page', 1), 1);

        $users = User::query()
            ->select([
                'id',
                'tenant_id',
                'role_id',
                'site_id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'is_active',
                'metadata',
                'last_login_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'role:id,name,slug',
                'role.permissions:id,slug',
                'permissions:id,slug',
                'tenant:id,name,business_type,country,phone,settings',
                'site:id,name,slug,description',
            ])
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($search, function ($query, $term) {
                $like = '%'.$term.'%';

                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->when($status, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($status === 'invited') {
                    $query->whereNull('last_login_at');
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($perPage, ['*'], 'page', $page);

        return UserResource::collection($users)
            ->additional([
                'meta' => $this->buildPaginationMeta($users),
            ])->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $request) {
            $site = Site::query()
                ->whereKey($data['site_id'])
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $request->user()->tenant_id))
                ->first();

            if (! $site) {
                throw ValidationException::withMessages([
                    'siteId' => ['Selected site is not available.'],
                ]);
            }

            $role = Role::query()->whereKey($data['role_id'])->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'roleId' => ['Selected role is not available.'],
                ]);
            }

            $passwordPlaceholder = Hash::make(Str::random(40));

            $user = User::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'role_id' => $role->id,
                'site_id' => $site->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'phone' => Arr::get($data, 'phone'),
                'is_active' => Arr::get($data, 'is_active', true),
                'metadata' => Arr::get($data, 'metadata'),
                'password' => $passwordPlaceholder,
            ]);

            return $user;
        });

        $user->load('role.permissions', 'permissions', 'tenant', 'site');

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManageUsers($request);
        $this->assertSameTenant($request, $user);

        $user->loadMissing('role.permissions', 'permissions', 'tenant', 'site');

        return (new UserResource($user))->response();
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->ensureCanManageUsers($request);
        $this->assertSameTenant($request, $user);

        $data = $request->validated();

        DB::transaction(function () use ($data, $user, $request) {
            if (Arr::has($data, 'site_id')) {
                $site = Site::query()
                    ->whereKey($data['site_id'])
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $request->user()->tenant_id))
                    ->first();

                if (! $site) {
                    throw ValidationException::withMessages([
                        'site_id' => ['Selected site is not available.'],
                    ]);
                }

                $user->site()->associate($site);
            }

            if (Arr::has($data, 'role_id')) {
                $role = Role::query()->whereKey($data['role_id'])->first();

                if (! $role) {
                    throw ValidationException::withMessages([
                        'role_id' => ['Selected role is not available.'],
                    ]);
                }

                $user->role()->associate($role);
            }

            $user->fill(Arr::only($data, [
                'first_name',
                'last_name',
                'phone',
            ]));

            if (Arr::has($data, 'email')) {
                $user->email = strtolower($data['email']);
            }

            if (Arr::has($data, 'is_active')) {
                $user->is_active = (bool) $data['is_active'];
            }

            if (Arr::has($data, 'metadata')) {
                $user->metadata = $data['metadata'];
            }

            $user->save();
        });

        $user->load('role.permissions', 'permissions', 'tenant', 'site');

        return (new UserResource($user))->response();
    }

    public function options(Request $request): JsonResponse
    {
        $this->ensureCanManageUsers($request);

        $tenantId = $request->user()->tenant_id;

        $sites = Site::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'sites' => $sites->map(fn ($site) => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'description' => $site->description,
            ])->values(),
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
        ]);
    }

    private function ensureCanManageUsers(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission('user.management')) {
            throw new AuthorizationException('You do not have permission to manage users.');
        }
    }

    private function assertSameTenant(Request $request, User $user): void
    {
        if ($user->tenant_id !== $request->user()->tenant_id) {
            throw ValidationException::withMessages([
                'user' => ['The requested user does not belong to the current tenant.'],
            ]);
        }
    }

    private function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'total' => $paginator->total(),
            'perPage' => $paginator->perPage(),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'hasNextPage' => $paginator->hasMorePages(),
        ];
    }

    private function sanitizePerPage(int $perPage): int
    {
        if ($perPage <= 0) {
            return 25;
        }

        return min($perPage, 100);
    }

    private function normalizeSearchTerm(?string $term): ?string
    {
        if ($term === null) {
            return null;
        }

        $trimmed = mb_substr(trim($term), 0, 120);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtolower($trimmed);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($status));

        return in_array($normalized, ['active', 'inactive', 'invited'], true)
            ? $normalized
            : null;
    }
}

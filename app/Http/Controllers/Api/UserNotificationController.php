<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated');

        $status = $this->normalizeStatus($request->query('status'));
        $perPage = $this->sanitizePerPage((int) $request->query('perPage', 10));

        $notifications = UserNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->when($status === 'unread', function (Builder $query) {
                $query->where('is_read', false);
            })
            ->latest()
            ->paginate($perPage);

        return UserNotificationResource::collection($notifications)->additional([
            'meta' => [
                'total' => $notifications->total(),
                'perPage' => $notifications->perPage(),
                'currentPage' => $notifications->currentPage(),
                'hasMorePages' => $notifications->hasMorePages(),
            ],
        ])->response();
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated');

        $count = UserNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread' => $count]);
    }

    public function markAsRead(Request $request, UserNotification $notification): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated');

        $this->ensureOwnership($notification, $user->id);

        $notification->markAsRead();

        return response()->json([
            'notification' => new UserNotificationResource($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated');

        UserNotification::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['updated' => true]);
    }

    private function ensureOwnership(UserNotification $notification, string $userId): void
    {
        abort_unless($notification->user_id === $userId, 404);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return in_array($normalized, ['all', 'unread'], true) ? $normalized : null;
    }

    private function sanitizePerPage(int $perPage): int
    {
        if ($perPage <= 0) {
            return 10;
        }

        return min($perPage, 50);
    }
}

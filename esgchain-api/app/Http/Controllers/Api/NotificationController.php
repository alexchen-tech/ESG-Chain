<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 中心廠內部使用者（admin/buyer/sustain/comply/analyst）站內通知。
 * 供應商 Portal 端另有 App\Http\Controllers\Api\Portal\PortalNotificationController，結構相同但路徑不同。
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $request->user()->notifications()
            ->paginate($request->input('per_page', 20), ['*'], 'page', $request->input('page', 1));

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /**
     * POST /api/v1/notifications/mark-read
     */
    public function markRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'message' => '已標記為已讀']);
    }

    /**
     * POST /api/v1/notifications/{id}/mark-read
     */
    public function markOneRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        abort_if(!$notification, 404, '通知不存在');

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => '已標記為已讀']);
    }
}

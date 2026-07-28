<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalNotificationController extends Controller
{
    /**
     * GET /api/v1/portal/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /**
     * POST /api/v1/portal/notifications/mark-read
     */
    public function markRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'message' => '已標記為已讀']);
    }
}

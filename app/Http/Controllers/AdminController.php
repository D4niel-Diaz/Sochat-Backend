<?php

namespace App\Http\Controllers;

use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private AdminService $adminService
    ) {}

    public function getMetrics(Request $request): JsonResponse
    {
        $metrics = $this->adminService->getMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    public function getActiveChats(Request $request): JsonResponse
    {
        $chats = $this->adminService->getActiveChats();

        return response()->json([
            'success' => true,
            'data' => $chats,
        ]);
    }

    public function getReports(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $reports = $status === 'pending'
            ? $this->adminService->getPendingReports()
            : $this->adminService->getReports();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function banGuest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => 'required|string|exists:guests,guest_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $result = $this->adminService->banGuest($request->input('guest_id'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ban guest',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest banned successfully',
        ]);
    }

    public function unbanGuest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'guest_id' => 'required|string|exists:guests,guest_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $result = $this->adminService->unbanGuest($request->input('guest_id'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unban guest',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest unbanned successfully',
        ]);
    }

    public function resolveReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_id' => 'required|integer|exists:reports,report_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $result = $this->adminService->resolveReport($request->input('report_id'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve report',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Report resolved successfully',
        ]);
    }

    public function getBannedGuests(Request $request): JsonResponse
    {
        $guests = $this->adminService->getBannedGuests();

        return response()->json([
            'success' => true,
            'data' => $guests,
        ]);
    }

    public function getFlaggedMessages(Request $request): JsonResponse
    {
        $messages = $this->adminService->getFlaggedMessages();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
}

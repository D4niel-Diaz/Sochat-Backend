<?php

namespace App\Http\Controllers;

use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(
        private GuestRepository $guestRepository,
        private ChatRepository $chatRepository,
        private ReportService $reportService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|exists:chats,chat_id',
            'reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = $this->guestRepository->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        $chat = $this->chatRepository->findById($request->input('chat_id'));
        if (!$chat || !$chat->isParticipant($guest->guest_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found or you are not a participant',
            ], 404);
        }

        $reportedGuestId = $chat->getPartnerId($guest->guest_id);
        if (!$reportedGuestId) {
            return response()->json([
                'success' => false,
                'message' => 'Could not identify reported user',
            ], 400);
        }

        $result = $this->reportService->createReport(
            $chat->chat_id,
            $guest->guest_id,
            $reportedGuestId,
            $request->input('reason'),
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['auto_banned']
                ? 'Report submitted and user has been banned'
                : 'Report submitted successfully',
        ], 201);
    }
}

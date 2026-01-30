<?php

namespace App\Http\Controllers;

use App\Repositories\GuestRepository;
use App\Services\ChatService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function __construct(
        private GuestRepository $guestRepository,
        private ChatService $chatService,
        private MessageService $messageService,
        private \App\Repositories\ChatRepository $chatRepository
    ) {}

    public function start(Request $request): JsonResponse
    {
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

        if ($guest->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => 'Guest is banned',
            ], 403);
        }

        if ($guest->expires_at && $guest->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired',
            ], 401);
        }

        $result = $this->chatService->findMatch($guest->guest_id);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Guest is banned or inactive',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['status'] === 'matched' 
                ? 'Chat started successfully' 
                : 'Waiting for a match',
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|exists:chats,chat_id',
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

        $result = $this->chatService->endChat($request->input('chat_id'), $guest->guest_id);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found or you are not a participant',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat ended successfully',
        ]);
    }

    public function messages(Request $request, int $chatId): JsonResponse
    {
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

        $limit = min($request->query('limit', 50), 100);
        $cursor = $request->query('cursor');

        $result = $this->messageService->getMessagesPaginated($chatId, $guest->guest_id, $limit, $cursor);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found or you are not a participant',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Messages retrieved successfully',
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        // CRITICAL: Enhanced input validation
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|exists:chats,chat_id|min:1',
            'content' => [
                'required',
                'string',
                'min:1', // Ensure non-empty
                'max:1000',
                function ($attribute, $value, $fail) {
                    // Reject empty or whitespace-only content
                    if (trim($value) === '') {
                        $fail('Message content cannot be empty.');
                    }
                    // Reject content that's only whitespace
                    if (strlen(trim($value)) === 0) {
                        $fail('Message content cannot be only whitespace.');
                    }
                },
            ],
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

        $message = $this->messageService->sendMessage(
            $request->input('chat_id'),
            $guest->guest_id,
            $request->input('content')
        );

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found, not a participant, or chat has ended',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Message sent successfully',
        ], 201);
    }

    public function typing(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|exists:chats,chat_id',
            'is_typing' => 'required|boolean',
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

        if ($guest->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => 'Guest is banned',
            ], 403);
        }

        $chat = $this->chatRepository->findById($request->input('chat_id'));
        if (!$chat || !$chat->isParticipant($guest->guest_id) || !$chat->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Chat not found or not active',
            ], 404);
        }

        event(new \App\Events\UserTyping(
            $request->input('chat_id'),
            $guest->guest_id,
            $request->input('is_typing')
        ));

        return response()->json([
            'success' => true,
            'message' => 'Typing status updated',
        ]);
    }
}

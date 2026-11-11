<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Services\ChatService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private MessageService $messageService
    ) {}

    public function start(Request $request): JsonResponse
    {
        /** @var Guest $guest set by the auth.guest middleware */
        $guest = $request->attributes->get('guest');

        // Matching parameters: request, then guest record, then presence row
        $role = $request->input('role') ?: $guest->role;
        $subject = $request->input('subject') ?: $guest->subject;
        $availability = $request->input('availability') ?: $guest->availability;

        if (!$role || !$subject || !$availability) {
            $presence = DB::table('presence')->where('guest_id', $guest->guest_id)->first();

            if ($presence) {
                $role = $role ?: $presence->role;
                $subject = $subject ?: $presence->subject;
                $availability = $availability ?: ($presence->availability ? json_decode($presence->availability, true) : null);
            }
        }

        $optInHint = 'Please opt in to matching first with role, subject, and availability.';

        if (!in_array($role, ['tutor', 'learner'], true)) {
            Log::warning('Match attempt without role', ['guest_id' => $guest->guest_id]);
            return $this->fail("Role is required. {$optInHint}", 422);
        }

        if (!is_string($subject) || trim($subject) === '') {
            Log::warning('Match attempt without subject', ['guest_id' => $guest->guest_id]);
            return $this->fail("Subject is required. {$optInHint}", 422);
        }

        if (!is_array($availability) || empty($availability)) {
            Log::warning('Match attempt without availability', ['guest_id' => $guest->guest_id]);
            return $this->fail("Availability is required. {$optInHint}", 422);
        }

        if ($request->hasAny(['role', 'subject', 'availability'])) {
            $guest->update([
                'role' => $role,
                'subject' => $subject,
                'availability' => $availability,
            ]);
        }

        $result = $this->chatService->findMatch($guest->guest_id, $role, $subject, $availability);

        if (!$result) {
            return $this->fail('Guest is banned or inactive', 403);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['status'] === 'matched'
                ? 'Chat started successfully'
                : ($result['message'] ?? 'Waiting for a match'),
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|exists:chats,chat_id',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $guest = $request->attributes->get('guest');

        if (!$this->chatService->endChat((int) $request->input('chat_id'), $guest->guest_id)) {
            return $this->fail('Chat not found or you are not a participant', 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat ended successfully',
        ]);
    }

    public function messages(Request $request, int $chat_id): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $guest = $request->attributes->get('guest');
        $limit = (int) $request->query('limit', 50);
        $cursor = $request->query('cursor') !== null ? (int) $request->query('cursor') : null;

        $result = $this->messageService->getMessagesPaginated($chat_id, $guest->guest_id, $limit, $cursor);

        if (!$result) {
            return $this->fail('Chat not found or you are not a participant', 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Messages retrieved successfully',
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|integer|min:1|exists:chats,chat_id',
            'content' => [
                'required',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && trim($value) === '') {
                        $fail('Message content cannot be empty.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $guest = $request->attributes->get('guest');

        $message = $this->messageService->sendMessage(
            (int) $request->input('chat_id'),
            $guest->guest_id,
            $request->input('content')
        );

        if (!$message) {
            return $this->fail('Chat not found, not a participant, or chat has ended', 404);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Message sent successfully',
        ], 201);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function validationFailed($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->toArray(),
        ], 422);
    }
}

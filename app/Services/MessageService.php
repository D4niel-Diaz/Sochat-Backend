<?php

namespace App\Services;

use App\Repositories\MessageRepository;
use App\Repositories\ChatRepository;
use Illuminate\Support\Facades\Config;

class MessageService
{
    protected MessageRepository $messageRepository;
    protected ChatRepository $chatRepository;

    public function __construct(MessageRepository $messageRepository, ChatRepository $chatRepository)
    {
        $this->messageRepository = $messageRepository;
        $this->chatRepository = $chatRepository;
    }

    public function sendMessage(int $chatId, string $senderGuestId, string $content): ?array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (!$chat || !$chat->isParticipant($senderGuestId) || !$chat->isActive()) {
            return null;
        }

        $sanitizedContent = $this->sanitizeContent($content);
        $isFlagged = $this->shouldFlag($sanitizedContent);

        $message = $this->messageRepository->create($chatId, $senderGuestId, $sanitizedContent, $isFlagged);

        return [
            'message_id' => $message->message_id,
            'content' => $sanitizedContent,
            'created_at' => $message->created_at->toIso8601String(),
            'is_flagged' => $isFlagged,
        ];
    }

    public function getMessages(int $chatId, string $guestId, int $limit = 100): ?array
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat || !$chat->isParticipant($guestId)) {
            return null;
        }

        $messages = $this->messageRepository->getMessagesByChatId($chatId, $limit);

        return [
            'messages' => $messages->map(fn ($msg) => $this->formatMessage($msg, $guestId))->toArray(),
        ];
    }

    public function getMessagesPaginated(int $chatId, string $guestId, int $limit = 50, ?int $cursor = null): ?array
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat || !$chat->isParticipant($guestId)) {
            return null;
        }

        $messages = $this->messageRepository->getMessagesPaginated($chatId, $limit, $cursor);

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages->pop();
        }

        // Cursor is the oldest message_id returned; pass it back to fetch older messages
        $nextCursor = $hasMore ? $messages->last()->message_id : null;

        return [
            'messages' => $messages->reverse()->values()->map(fn ($msg) => $this->formatMessage($msg, $guestId))->toArray(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            // Lets polling clients notice when the partner ended the chat
            'chat_status' => $chat->isActive() ? 'active' : 'ended',
        ];
    }

    private function formatMessage($msg, string $guestId): array
    {
        return [
            'message_id' => $msg->message_id,
            'sender' => $msg->sender_guest_id === $guestId ? 'you' : 'partner',
            'content' => $msg->content,
            'created_at' => $msg->created_at->toIso8601String(),
            'is_flagged' => $msg->is_flagged,
        ];
    }

    /**
     * Flag messages for admin review. Clear personal info is normally rejected
     * earlier by the ContentFilter middleware; it is checked here too in case a
     * message reaches the service another way.
     */
    protected function shouldFlag(string $content): bool
    {
        $patterns = array_merge(
            Config::get('moderation.flag_patterns', []),
            Config::get('moderation.personal_info_patterns', [])
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store text exactly as typed (trimmed). Output escaping is the client's job:
     * React renders message content as text. strip_tags is not safe here because
     * it deletes ordinary text such as "i <3 you" or "x<y and y>z".
     */
    protected function sanitizeContent(string $content): string
    {
        return trim($content);
    }
}

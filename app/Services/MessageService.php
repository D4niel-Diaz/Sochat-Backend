<?php

namespace App\Services;

use App\Repositories\MessageRepository;
use App\Repositories\ChatRepository;
use App\Events\MessageSent;
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
        $isFlagged = $this->detectPersonalInfo($sanitizedContent);

        $message = $this->messageRepository->create($chatId, $senderGuestId, $sanitizedContent);

        if ($isFlagged) {
            $this->messageRepository->flagMessage($message->message_id);
        }

        event(new MessageSent($chat, $message));

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
            'messages' => $messages->map(function ($msg) use ($guestId) {
                return [
                    'message_id' => $msg->message_id,
                    'sender' => $msg->sender_guest_id === $guestId ? 'you' : 'partner',
                    'content' => $msg->content,
                    'created_at' => $msg->created_at->toIso8601String(),
                    'is_flagged' => $msg->is_flagged,
                ];
            })->toArray(),
        ];
    }

    public function getMessagesPaginated(int $chatId, string $guestId, int $limit = 50, ?string $cursor = null): ?array
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

        $nextCursor = $hasMore ? $messages->last()->created_at->toIso8601String() : null;

        return [
            'messages' => $messages->reverse()->map(function ($msg) use ($guestId) {
                return [
                    'message_id' => $msg->message_id,
                    'sender' => $msg->sender_guest_id === $guestId ? 'you' : 'partner',
                    'content' => $msg->content,
                    'created_at' => $msg->created_at->toIso8601String(),
                    'is_flagged' => $msg->is_flagged,
                ];
            })->toArray(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    protected function filterContent(string $content): string
    {
        $bannedWords = Config::get('moderation.banned_words', []);
        $filtered = $content;

        foreach ($bannedWords as $word) {
            $filtered = str_ireplace($word, str_repeat('*', strlen($word)), $filtered);
        }

        return $filtered;
    }

    protected function detectPersonalInfo(string $content): bool
    {
        $patterns = Config::get('moderation.personal_info_patterns', []);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function sanitizeContent(string $content): string
    {
        $sanitized = strip_tags($content);
        
        $sanitized = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $sanitized);
        $sanitized = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $sanitized);
        $sanitized = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $sanitized);
        $sanitized = preg_replace('/<embed\b[^>]*>/i', '', $sanitized);
        $sanitized = preg_replace('/javascript:/i', '', $sanitized);
        $sanitized = preg_replace('/on\w+\s*=/i', '', $sanitized);
        
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        
        return trim($sanitized);
    }
}

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
        $this->chatRepository    = $chatRepository;
    }

    public function sendMessage(int $chatId, string $senderGuestId, string $content): ?array
    {
        $chat = $this->chatRepository->findById($chatId);

        if (!$chat || !$chat->isParticipant($senderGuestId) || !$chat->isActive()) {
            return null;
        }

        // FIX: Apply full content pipeline in correct order:
        // 1. Strip all HTML (XSS prevention)
        // 2. Apply banned-word filter (was previously never called — bug fixed)
        // 3. Detect personal info (for flagging)
        $sanitizedContent = $this->sanitizeContent($content);
        $filteredContent  = $this->filterContent($sanitizedContent);
        $isFlagged        = $this->detectPersonalInfo($filteredContent);

        $message = $this->messageRepository->create($chatId, $senderGuestId, $filteredContent);

        if ($isFlagged) {
            $this->messageRepository->flagMessage($message->message_id);
        }

        event(new MessageSent($chat, $message));

        return [
            'message_id' => $message->message_id,
            'content'    => $filteredContent,
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
            'messages' => $messages->map(fn($msg) => [
                'message_id' => $msg->message_id,
                'sender'     => $msg->sender_guest_id === $guestId ? 'you' : 'partner',
                'content'    => $msg->content,
                'created_at' => $msg->created_at->toIso8601String(),
                'is_flagged' => $msg->is_flagged,
            ])->toArray(),
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
            'messages'    => $messages->reverse()->map(fn($msg) => [
                'message_id' => $msg->message_id,
                'sender'     => $msg->sender_guest_id === $guestId ? 'you' : 'partner',
                'content'    => $msg->content,
                'created_at' => $msg->created_at->toIso8601String(),
                'is_flagged' => $msg->is_flagged,
            ])->toArray(),
            'next_cursor' => $nextCursor,
            'has_more'    => $hasMore,
        ];
    }

    /**
     * Apply banned-word filter (censors matched words with asterisks).
     * FIX: This method was defined but never called in sendMessage — now wired in.
     */
    protected function filterContent(string $content): string
    {
        $bannedWords = Config::get('moderation.banned_words', []);
        $filtered    = $content;

        foreach ($bannedWords as $word) {
            $filtered = str_ireplace($word, str_repeat('*', mb_strlen($word)), $filtered);
        }

        return $filtered;
    }

    /**
     * Detect personal information patterns (phones, emails, etc.) for flagging.
     */
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

    /**
     * Strip HTML and encode for safe storage.
     *
     * FIX: Removed redundant regex patterns — strip_tags() already removes
     * <script>, <iframe>, <object>, <embed> etc. The follow-up regexes
     * on an already-stripped string were wasted cycles.
     */
    protected function sanitizeContent(string $content): string
    {
        // Remove all HTML tags (prevents XSS at input level)
        $sanitized = strip_tags($content);

        // Encode special characters for safe HTML output / storage
        // ENT_QUOTES covers both single and double quotes
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($sanitized);
    }
}

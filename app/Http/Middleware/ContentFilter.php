<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pre-filters message content BEFORE it reaches the controller.
 *
 * FIX — Consolidated responsibility:
 * - ContentFilter middleware: handles ONLY banned-word substitution (mutates `content`)
 * - MessageService:           handles sanitization (strip_tags) + flagging personal info
 *
 * Previously, ContentFilter ALSO blocked messages with personal info (returned 400),
 * while MessageService only flagged the same content. These two conflicting behaviours
 * (block vs. flag) are now resolved: blocking is removed from middleware; MessageService
 * is the single source of truth for flagging.
 */
class ContentFilter
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('content') || $request->has('message')) {
            $content = $request->input('content') ?? $request->input('message');

            if ($content !== null && $content !== '') {
                $filtered = $this->filterBannedWords($content);

                if ($filtered !== $content) {
                    $request->merge([
                        'content' => $filtered,
                        'message' => $filtered,
                    ]);
                }
            }
        }

        return $next($request);
    }

    /**
     * Replaces banned words with asterisks.
     * PersonalInfo detection (flagging) is handled by MessageService, not here.
     */
    protected function filterBannedWords(string $content): string
    {
        $bannedWords = Config::get('moderation.banned_words', []);
        $filtered    = $content;

        foreach ($bannedWords as $word) {
            $filtered = str_ireplace($word, str_repeat('*', mb_strlen($word)), $filtered);
        }

        return $filtered;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class ContentFilter
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('content') || $request->has('message')) {
            $content = $request->input('content') ?? $request->input('message');

            // Only filter if content is not null
            if ($content !== null && $content !== '') {
                $filteredContent = $this->filterContent($content);

                if ($filteredContent !== $content) {
                    $request->merge([
                        'content' => $filteredContent,
                        'message' => $filteredContent,
                    ]);
                }

                if ($this->detectPersonalInfo($filteredContent)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Message contains personal information and cannot be sent',
                    ], 400);
                }
            }
        }

        return $next($request);
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
        $patterns = Config::get('moderation.personal_info_patterns', [
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', 
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        ]);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}

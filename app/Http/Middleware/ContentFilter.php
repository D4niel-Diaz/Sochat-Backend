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
        $content = $request->input('content');

        if (is_string($content) && $content !== '') {
            $filteredContent = $this->filterContent($content);

            if ($filteredContent !== $content) {
                $request->merge(['content' => $filteredContent]);
            }

            if ($this->detectPersonalInfo($filteredContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message contains personal information and cannot be sent',
                ], 400);
            }
        }

        return $next($request);
    }

    protected function filterContent(string $content): string
    {
        foreach (Config::get('moderation.banned_words', []) as $word) {
            $content = preg_replace_callback(
                '/\b' . preg_quote($word, '/') . '\b/iu',
                fn ($m) => str_repeat('*', mb_strlen($m[0])),
                $content
            );
        }

        return $content;
    }

    protected function detectPersonalInfo(string $content): bool
    {
        foreach (Config::get('moderation.personal_info_patterns', []) as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneChatMessages extends Command
{
    protected $signature = 'chats:prune-messages';
    protected $description = 'Delete messages from ended chats once the retention period has passed';

    public function handle()
    {
        $cutoff = now()->subMinutes(config('moderation.message_retention_minutes', 60));
        $hardCutoff = now()->subDays(config('moderation.reported_message_retention_days', 30));

        // Ended chats past retention, unless a report is still open (admins need
        // the messages to review it). Past the hard limit, delete regardless.
        $query = Chat::query()
            ->where('status', 'ended')
            ->whereNotNull('ended_at')
            ->where(function ($q) use ($cutoff, $hardCutoff) {
                $q->where(function ($q) use ($cutoff) {
                    $q->where('ended_at', '<=', $cutoff)
                        ->whereDoesntHave('reports', fn ($r) => $r->where('status', '!=', 'resolved'));
                })->orWhere('ended_at', '<=', $hardCutoff);
            })
            ->whereHas('messages');

        $deleted = 0;
        $query->select('chat_id')->chunkById(500, function ($chats) use (&$deleted) {
            $deleted += Message::whereIn('chat_id', $chats->pluck('chat_id'))->delete();
        }, 'chat_id');

        if ($deleted > 0) {
            Log::info('Pruned chat messages', ['deleted' => $deleted]);
        }
        $this->info("Deleted {$deleted} messages from ended chats.");

        return Command::SUCCESS;
    }
}

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

        // Ended chats past retention with no open report (admins need those to
        // review it). Flagged messages nobody has reviewed yet are kept too.
        $deleted = $this->deleteMessages(
            $this->endedChats()
                ->where('ended_at', '<=', $cutoff)
                ->whereDoesntHave('reports', fn ($r) => $r->where('status', '!=', 'resolved')),
            fn ($q) => $q->where(fn ($q) => $q->where('is_flagged', false)->orWhereNotNull('reviewed_at'))
        );

        // Past the hard limit everything goes, reviewed or not
        $deleted += $this->deleteMessages($this->endedChats()->where('ended_at', '<=', $hardCutoff));

        if ($deleted > 0) {
            Log::info('Pruned chat messages', ['deleted' => $deleted]);
        }
        $this->info("Deleted {$deleted} messages from ended chats.");

        return Command::SUCCESS;
    }

    private function endedChats()
    {
        return Chat::query()->where('status', 'ended')->whereNotNull('ended_at');
    }

    /** Delete the matching messages of every chat in $chats; returns how many. */
    private function deleteMessages($chats, ?callable $scope = null): int
    {
        $deleted = 0;
        $chats->whereHas('messages', fn ($q) => $scope ? $scope($q) : $q)
            ->select('chat_id')
            ->chunkById(500, function ($batch) use ($scope, &$deleted) {
                $query = Message::whereIn('chat_id', $batch->pluck('chat_id'));
                if ($scope) {
                    $scope($query);
                }
                $deleted += $query->delete();
            }, 'chat_id');

        return $deleted;
    }
}

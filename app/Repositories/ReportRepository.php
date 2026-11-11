<?php

namespace App\Repositories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

class ReportRepository
{
    private const ADMIN_RELATIONS = [
        'chat:chat_id,guest_id_1,guest_id_2',
        'reporter:guest_id,ip_address',
        'reported:guest_id,ip_address',
    ];

    public function create(int $chatId, string $reporterGuestId, string $reportedGuestId, string $reason, ?string $ipAddress = null): Report
    {
        return Report::create([
            'chat_id' => $chatId,
            'reporter_guest_id' => $reporterGuestId,
            'reported_guest_id' => $reportedGuestId,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'status' => 'pending',
        ]);
    }

    public function findById(int $reportId): ?Report
    {
        return Report::find($reportId);
    }

    public function getPendingReports(): Collection
    {
        return Report::pending()
            ->with(self::ADMIN_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAllReports(): Collection
    {
        return Report::with(self::ADMIN_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateStatus(int $reportId, string $status): bool
    {
        return Report::where('report_id', $reportId)->update(['status' => $status]) > 0;
    }

    public function countAllReports(): int
    {
        return Report::count();
    }

    public function countPendingReports(): int
    {
        return Report::pending()->count();
    }

    public function existsForChatAndReporter(int $chatId, string $reporterGuestId): bool
    {
        return Report::where('chat_id', $chatId)
            ->where('reporter_guest_id', $reporterGuestId)
            ->exists();
    }

    public function countDistinctReportersByGuestId(string $guestId): int
    {
        return Report::where('reported_guest_id', $guestId)
            ->distinct()
            ->count('reporter_guest_id');
    }
}

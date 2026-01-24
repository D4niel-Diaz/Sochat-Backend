<?php

namespace App\Repositories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

class ReportRepository
{
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
            ->with(['chat:chat_id,guest_id_1,guest_id_2', 'reporter:guest_id,ip_address', 'reported:guest_id,ip_address'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAllReports(): Collection
    {
        return Report::with(['chat:chat_id,guest_id_1,guest_id_2', 'reporter:guest_id,ip_address', 'reported:guest_id,ip_address'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateStatus(int $reportId, string $status): bool
    {
        return Report::where('report_id', $reportId)->update(['status' => $status]) > 0;
    }

    public function getReportsByGuestId(string $guestId): Collection
    {
        return Report::where('reporter_guest_id', $guestId)
            ->orWhere('reported_guest_id', $guestId)
            ->with(['chat'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countPendingReports(): int
    {
        return Report::pending()->count();
    }

    public function countReportsByGuestId(string $guestId): int
    {
        return Report::where('reported_guest_id', $guestId)->count();
    }

    public function countPendingReportsByGuestId(string $guestId): int
    {
        return Report::where('reported_guest_id', $guestId)
            ->where('status', 'pending')
            ->count();
    }
}

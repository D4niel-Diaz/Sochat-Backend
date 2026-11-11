<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use App\Repositories\GuestRepository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ReportService
{
    protected ReportRepository $reportRepository;
    protected GuestRepository $guestRepository;

    public function __construct(ReportRepository $reportRepository, GuestRepository $guestRepository)
    {
        $this->reportRepository = $reportRepository;
        $this->guestRepository = $guestRepository;
    }

    /**
     * Returns null when this reporter has already reported this chat.
     */
    public function createReport(int $chatId, string $reporterGuestId, string $reportedGuestId, string $reason, ?string $ipAddress = null): ?array
    {
        if ($this->reportRepository->existsForChatAndReporter($chatId, $reporterGuestId)) {
            return null;
        }

        $report = $this->reportRepository->create($chatId, $reporterGuestId, $reportedGuestId, $reason, $ipAddress);

        // Count distinct reporters so one person can't ban someone by reporting repeatedly
        $reporterCount = $this->reportRepository->countDistinctReportersByGuestId($reportedGuestId);
        $threshold = (int) Config::get('moderation.report_threshold', 3);
        $autoBanned = $reporterCount >= $threshold;

        if ($autoBanned) {
            $this->guestRepository->banGuest($reportedGuestId);
            Log::info('Auto-banned guest after reports from distinct users', [
                'guest_id' => $reportedGuestId,
                'reporters' => $reporterCount,
            ]);
        }

        return [
            'report_id' => $report->report_id,
            'status' => $report->status,
            'auto_banned' => $autoBanned,
        ];
    }
}

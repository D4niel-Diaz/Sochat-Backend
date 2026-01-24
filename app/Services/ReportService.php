<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use App\Repositories\GuestRepository;
use App\Events\UserReported;
use App\Jobs\ProcessReport;

class ReportService
{
    protected ReportRepository $reportRepository;
    protected GuestRepository $guestRepository;

    public function __construct(ReportRepository $reportRepository, GuestRepository $guestRepository)
    {
        $this->reportRepository = $reportRepository;
        $this->guestRepository = $guestRepository;
    }

    public function createReport(int $chatId, string $reporterGuestId, string $reportedGuestId, string $reason, ?string $ipAddress = null): ?array
    {
        $report = $this->reportRepository->create($chatId, $reporterGuestId, $reportedGuestId, $reason, $ipAddress);

        event(new UserReported($report));

        ProcessReport::dispatch($report->report_id);

        $reportCount = $this->reportRepository->countReportsByGuestId($reportedGuestId);

        if ($reportCount >= 3) {
            $this->guestRepository->banGuest($reportedGuestId);
        }

        return [
            'report_id' => $report->report_id,
            'status' => $report->status,
            'auto_banned' => $reportCount >= 3,
        ];
    }

    public function getPendingReports(): array
    {
        return $this->reportRepository->getPendingReports()->toArray();
    }

    public function getAllReports(): array
    {
        return $this->reportRepository->getAllReports()->toArray();
    }

    public function updateReportStatus(int $reportId, string $status): bool
    {
        return $this->reportRepository->updateStatus($reportId, $status);
    }

    public function getReportCount(): int
    {
        return $this->reportRepository->countPendingReports();
    }
}

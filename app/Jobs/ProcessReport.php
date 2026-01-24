<?php

namespace App\Jobs;

use App\Models\Report;
use App\Repositories\GuestRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $reportId
    ) {}

    public function handle(GuestRepository $guestRepository, \App\Repositories\ReportRepository $reportRepository): void
    {
        $report = Report::find($this->reportId);

        if (!$report) {
            Log::warning("Report not found: {$this->reportId}");
            return;
        }

        Log::info("Processing report #{$report->report_id} for guest {$report->reported_guest_id}");

        $reportCount = $reportRepository->countPendingReportsByGuestId($report->reported_guest_id);

        if ($reportCount >= 3) {
            $guestRepository->banGuest($report->reported_guest_id);
            Log::info("Auto-banned guest {$report->reported_guest_id} due to multiple reports");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to process report #{$this->reportId}: " . $exception->getMessage());
    }
}

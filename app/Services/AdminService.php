<?php

namespace App\Services;

use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use App\Repositories\ReportRepository;
use App\Repositories\MessageRepository;

class AdminService
{
    protected GuestRepository $guestRepository;
    protected ChatRepository $chatRepository;
    protected ReportRepository $reportRepository;
    protected MessageRepository $messageRepository;

    public function __construct(
        GuestRepository $guestRepository,
        ChatRepository $chatRepository,
        ReportRepository $reportRepository,
        MessageRepository $messageRepository
    ) {
        $this->guestRepository = $guestRepository;
        $this->chatRepository = $chatRepository;
        $this->reportRepository = $reportRepository;
        $this->messageRepository = $messageRepository;
    }

    public function getMetrics(): array
    {
        return [
            'online_users' => app(\App\Services\PresenceService::class)->countOnlineUsers(),
            'active_chats' => $this->chatRepository->countActiveChats(),
            'total_reports' => $this->reportRepository->getAllReports()->count(),
            'pending_reports' => $this->reportRepository->countPendingReports(),
            'banned_users' => $this->guestRepository->getBannedGuests()->count(),
        ];
    }

    public function getActiveChats(): array
    {
        return $this->chatRepository->getActiveChats()->toArray();
    }

    public function getReports(): array
    {
        return $this->reportRepository->getAllReports()->toArray();
    }

    public function getPendingReports(): array
    {
        return $this->reportRepository->getPendingReports()->toArray();
    }

    public function banGuest(string $guestId): bool
    {
        return $this->guestRepository->banGuest($guestId);
    }

    public function unbanGuest(string $guestId): bool
    {
        return $this->guestRepository->unbanGuest($guestId);
    }

    public function resolveReport(int $reportId): bool
    {
        return $this->reportRepository->updateStatus($reportId, 'resolved');
    }

    public function getBannedGuests(): array
    {
        return $this->guestRepository->getBannedGuests()->toArray();
    }

    public function getFlaggedMessages(): array
    {
        return $this->messageRepository->getFlaggedMessages()->toArray();
    }
}

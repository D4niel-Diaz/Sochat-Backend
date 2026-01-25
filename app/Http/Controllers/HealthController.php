<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        $checks['database'] = $this->checkDatabase();
        $checks['redis'] = $this->checkRedis();
        $checks['queue'] = $this->checkQueue();

        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $status = 'unhealthy';
                break;
            }
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $status === 'healthy' ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            return [
                'status' => 'healthy',
                'message' => 'Redis not configured (using database cache)',
            ];
        }

        try {
            Redis::ping();
            return [
                'status' => 'healthy',
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'healthy',
                'message' => 'Redis not available (using database cache)',
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $pendingJobs = DB::table('jobs')
                ->where('reserved_at', null)
                ->count();
            
            return [
                'status' => 'healthy',
                'message' => 'Queue operational',
                'pending_jobs' => $pendingJobs,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage(),
            ];
        }
    }
}

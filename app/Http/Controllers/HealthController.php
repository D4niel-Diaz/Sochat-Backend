<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] !== 'unhealthy');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'message' => 'Database connection failed'];
        }
    }

    private function checkRedis(): array
    {
        $usesRedis = config('cache.default') === 'redis' || config('queue.default') === 'redis';

        if (!$usesRedis) {
            return ['status' => 'skipped', 'message' => 'Redis is not configured for cache or queue'];
        }

        try {
            Redis::connection()->ping();
            return ['status' => 'healthy', 'message' => 'Redis connection successful'];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'message' => 'Redis is configured but unreachable'];
        }
    }

    private function checkQueue(): array
    {
        $connection = config('queue.default');

        if ($connection !== 'database') {
            return ['status' => 'skipped', 'message' => "Queue driver is '{$connection}'"];
        }

        try {
            return [
                'status' => 'healthy',
                'message' => 'Queue operational',
                'pending_jobs' => DB::table('jobs')->whereNull('reserved_at')->count(),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'unhealthy', 'message' => 'Queue check failed'];
        }
    }
}

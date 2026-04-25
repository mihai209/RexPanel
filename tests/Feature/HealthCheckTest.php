<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Check if SQLite database is reachable and functional.
     */
    public function test_sqlite_connection(): void
    {
        $this->assertTrue(DB::connection()->getDatabaseName() !== null);
        $this->assertNotNull(DB::connection()->getPdo());
    }

    /**
     * Check if Redis is reachable and responsive.
     */
    public function test_redis_connection(): void
    {
        try {
            $response = Redis::connection()->ping();
            // Depending on the driver, ping() might return 'PONG' or a boolean/object
            $this->assertTrue($response == 'PONG' || $response === true || !empty($response));
        } catch (\Exception $e) {
            $this->fail("Redis connection failed: " . $e->getMessage());
        }
    }
}

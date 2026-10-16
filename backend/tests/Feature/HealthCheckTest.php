<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_subsystems_healthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'checks' => [
                    'database' => 'ok',
                    'redis'    => 'ok',
                    'storage'  => 'ok',
                ],
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'checks' => [
                    'database',
                    'redis',
                    'storage',
                ],
            ]);
    }

    public function test_api_health_endpoint_alias_works(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ]);
    }

    public function test_health_endpoint_returns_503_degraded_when_database_fails(): void
    {
        // Simulate DB failure
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \PDOException('Connection lost'));

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'database' => 'fail',
                ],
            ]);
    }
}

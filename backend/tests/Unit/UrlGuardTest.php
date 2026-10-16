<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\Exceptions\UrlGuardException;
use App\Services\Security\UrlGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlGuardTest extends TestCase
{
    private UrlGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new UrlGuard();
        UrlGuard::resetDnsResolver();
    }

    protected function tearDown(): void
    {
        UrlGuard::resetDnsResolver();
        parent::tearDown();
    }

    public function test_rejects_plain_http_protocol(): void
    {
        $this->expectException(UrlGuardException::class);
        $this->expectExceptionMessage("must use secure HTTPS protocol");

        try {
            $this->guard->validateUrl('http://example.com');
        } catch (UrlGuardException $e) {
            $this->assertEquals('NOT_HTTPS', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_localhost(): void
    {
        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://localhost');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_loopback_ip_127_0_0_1(): void
    {
        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://127.0.0.1');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_cloud_metadata_ip_169_254_169_254(): void
    {
        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://169.254.169.254');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_private_class_a_network_10_x(): void
    {
        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://10.0.0.1');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_private_class_c_network_192_168_x(): void
    {
        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://192.168.1.1');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_dns_rebinding_style_resolution_to_internal_ip(): void
    {
        // Simulate a DNS record that points to 127.0.0.1 or 10.0.0.5
        UrlGuard::setDnsResolver(function (string $host) {
            if ($host === 'rebound.attacker.com') {
                return ['127.0.0.1'];
            }
            return ['93.184.216.34'];
        });

        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->validateUrl('https://rebound.attacker.com');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_blocks_redirect_to_internal_ip(): void
    {
        // Mock external host resolving to public IP
        UrlGuard::setDnsResolver(function (string $host) {
            if ($host === 'public-store.com') {
                return ['93.184.216.34'];
            }
            return [$host];
        });

        // Initial request returns a 302 redirect pointing to internal loopback
        Http::fake([
            'https://public-store.com/wp-json/wc/v3/system_status' => Http::response(
                '',
                302,
                ['Location' => 'https://127.0.0.1/admin']
            ),
        ]);

        $this->expectException(UrlGuardException::class);

        try {
            $this->guard->get('https://public-store.com/wp-json/wc/v3/system_status');
        } catch (UrlGuardException $e) {
            $this->assertEquals('REST_BLOCKED', $e->errorCode);
            throw $e;
        }
    }

    public function test_enforces_redirect_limit(): void
    {
        UrlGuard::setDnsResolver(fn () => ['93.184.216.34']);

        // Infinite redirect loop
        Http::fake([
            'https://public-store.com/*' => Http::response(
                '',
                302,
                ['Location' => 'https://public-store.com/next']
            ),
        ]);

        $this->expectException(UrlGuardException::class);
        $this->expectExceptionMessage("Redirect limit of 3 hops exceeded");

        $this->guard->get('https://public-store.com/wp-json');
    }

    public function test_enforces_response_size_cap(): void
    {
        UrlGuard::setDnsResolver(fn () => ['93.184.216.34']);

        // Mock 6 MB response (exceeding 5 MB cap)
        $giantPayload = str_repeat('A', 6 * 1024 * 1024);

        Http::fake([
            'https://public-store.com/huge' => Http::response(
                $giantPayload,
                200,
                ['Content-Length' => (string) strlen($giantPayload)]
            ),
        ]);

        $this->expectException(UrlGuardException::class);
        $this->expectExceptionMessage("Response size exceeded maximum allowed limit");

        $this->guard->get('https://public-store.com/huge');
    }

    public function test_allows_valid_public_https_url(): void
    {
        UrlGuard::setDnsResolver(fn () => ['93.184.216.34']); // public IP of example.com

        $validated = $this->guard->validateUrl('https://example.com');
        $this->assertEquals('https://example.com', $validated);
    }
}

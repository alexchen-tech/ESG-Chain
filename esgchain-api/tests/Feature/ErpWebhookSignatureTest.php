<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * 8.4 Webhook 端點驗證：有效 / 無效 HMAC signature
 */
class ErpWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret-12345';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('erp.auth_mode', 'hmac');
        Config::set('erp.webhook_secret', self::WEBHOOK_SECRET);
    }

    public function test_valid_hmac_signature_accepted(): void
    {
        $payload   = json_encode(['erp_code' => 'SUP-001', 'name' => '測試供應商']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);

        $response = $this->withHeaders([
            'Content-Type'    => 'application/json',
            'X-ERP-Signature' => $signature,
        ])->postJson('/api/v1/erp/webhook/suppliers', json_decode($payload, true));

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
    }

    public function test_invalid_hmac_signature_rejected(): void
    {
        $payload = json_encode(['erp_code' => 'SUP-001', 'name' => '測試供應商']);

        $response = $this->withHeaders([
            'Content-Type'    => 'application/json',
            'X-ERP-Signature' => 'sha256=invalid_signature_here',
        ])->postJson('/api/v1/erp/webhook/suppliers', json_decode($payload, true));

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Invalid signature']);
    }

    public function test_missing_signature_rejected(): void
    {
        $payload = json_encode(['erp_code' => 'SUP-001']);

        $response = $this->withHeaders([
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/erp/webhook/suppliers', json_decode($payload, true));

        $response->assertStatus(401);
    }

    public function test_unsupported_entity_returns_422(): void
    {
        $payload   = json_encode(['foo' => 'bar']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);

        $response = $this->withHeaders([
            'Content-Type'    => 'application/json',
            'X-ERP-Signature' => $signature,
        ])->postJson('/api/v1/erp/webhook/unknown-entity', json_decode($payload, true));

        $response->assertStatus(422);
    }
}

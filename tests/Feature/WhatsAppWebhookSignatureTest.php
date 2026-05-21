<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Security-critical regression cover for the WhatsApp webhook endpoint.
 *
 * The webhook at /api/whatsapp/webhook accepts BOTH:
 *   GET  — Meta verification handshake (hub.verify_token must match)
 *   POST — incoming message payload (X-Hub-Signature-256 must HMAC-validate)
 *
 * Without these guards, any internet caller could:
 *   - hijack the handshake and steal the webhook URL
 *   - inject fake "incoming messages" that the BookingFlowService would treat
 *     as real and act on (create bookings, send messages back, etc.)
 *
 * These tests pin the verification behavior so it can't silently regress.
 */
class WhatsAppWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected string $verifyToken = 'test-verify-token-secret';

    protected string $appSecret = 'test-app-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.whatsapp.verify_token', $this->verifyToken);
        Config::set('services.whatsapp.app_secret', $this->appSecret);
    }

    // -------------------------------------------------------------------------
    // GET — Meta verification handshake
    // -------------------------------------------------------------------------

    public function test_get_returns_challenge_when_token_matches(): void
    {
        $challenge = 'meta-handshake-12345';

        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token='
            .urlencode($this->verifyToken).'&hub_challenge='.urlencode($challenge));

        $response->assertStatus(200);
        $this->assertSame($challenge, $response->getContent(),
            'Meta expects the raw challenge string in the response body');
    }

    public function test_get_rejects_wrong_verify_token(): void
    {
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=WRONG&hub_challenge=x');

        // Controller now hash_equals()es the token itself (the Netflie SDK
        // echoed the challenge even on wrong tokens). A wrong token must
        // produce a 403 with NO challenge echo — anything else would let
        // Meta register the subscription against an attacker's app.
        $response->assertStatus(403);
        $this->assertNotEquals('x', $response->getContent(),
            'Wrong verify_token must NOT yield the challenge echo');
    }

    // -------------------------------------------------------------------------
    // POST — HMAC signature verification
    // -------------------------------------------------------------------------

    public function test_post_rejects_invalid_signature(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];

        $response = $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256=invalid_bad_signature_value',
        ]);

        $response->assertStatus(401);
    }

    public function test_post_accepts_valid_hmac_signature(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $body = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        $response = $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],         // params
            [],         // cookies
            [],         // files
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $sig,
            ],
            $body
        );

        // Valid signature → not a 401. Controller may return 200/204 or
        // whatever the handler decides; the contract here is "not rejected".
        $this->assertNotSame(401, $response->getStatusCode(),
            'Valid HMAC signature must pass the verification gate');
    }

    public function test_post_rejects_legacy_sha1_with_wrong_secret(): void
    {
        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];
        $body = json_encode($payload);
        // SHA1 with wrong secret — must be rejected.
        $sig = 'sha1='.hash_hmac('sha1', $body, 'wrong-secret');

        $response = $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => $sig,
            ],
            $body
        );

        $response->assertStatus(401);
    }
}

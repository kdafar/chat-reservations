<?php

namespace Tests\Feature;

use App\Models\WAMessageLog;
use App\Services\BookingFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Behavior coverage for the current WhatsApp webhook handler.
 *
 * The deleted WhatsAppWebhookTest / WhatsAppMessageHandlerTest covered an
 * earlier per-slug routing + FlowVersion-screen architecture that no longer
 * exists (the system now has a single global endpoint that dispatches to
 * BookingFlowService). Restoring them verbatim would test code that isn't
 * there.
 *
 * Instead, these tests pin the handler's *current* contract:
 *   - valid signed payload with messages → BookingFlowService::handle() is invoked
 *   - duplicate wa_message_id → handler is NOT invoked again (idempotency)
 *   - delivery-status payloads → handler is NOT invoked (logging only)
 *   - empty messages array → handler is NOT invoked
 *
 * The signature/handshake security gates live in WhatsAppWebhookSignatureTest.
 */
class WhatsAppWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected string $appSecret = 'handler-test-secret';

    /** Tracks how many times BookingFlowService::handle() was invoked. */
    public int $handleCalls = 0;

    /** Last payload BookingFlowService received (for assertions). */
    public ?array $lastHandlePayload = null;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.whatsapp.app_secret', $this->appSecret);

        // Replace BookingFlowService with a spy so the test doesn't need a
        // session/flow stack and we can assert on dispatch precisely.
        $self = $this;
        $spy = new class($self) extends BookingFlowService
        {
            public function __construct(public WhatsAppWebhookHandlerTest $t)
            {
                // skip parent ctor; we never call any real dep.
            }

            public function handle(array $payload): void
            {
                $this->t->handleCalls++;
                $this->t->lastHandlePayload = $payload;
            }
        };
        $this->app->instance(BookingFlowService::class, $spy);

        // Short-circuit the rate-limit cache & locking by disabling it.
        \App\Support\Settings::flushCache();
        \Illuminate\Support\Facades\Config::set('services.whatsapp.rate_limit.enabled', false);
    }

    /** Sign a JSON body the way Meta would. */
    private function postSigned(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $sig = 'sha256='.hash_hmac('sha256', $body, $this->appSecret);

        return $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $sig,
            ],
            $body
        );
    }

    /** Build a minimal valid message payload. */
    private function messagePayload(string $wamid, string $from = '96599887766', string $text = 'hello'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111', 'display_phone_number' => '+96522220000'],
                        'messages' => [[
                            'id' => $wamid,
                            'from' => $from,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_valid_message_dispatches_to_booking_flow(): void
    {
        $resp = $this->postSigned($this->messagePayload('wamid.AAA1'));

        $this->assertNotSame(401, $resp->getStatusCode(), 'Signature should pass');
        $this->assertSame(1, $this->handleCalls, 'BookingFlowService::handle() must be called exactly once');
        $this->assertSame('wamid.AAA1', $this->lastHandlePayload['entry'][0]['changes'][0]['value']['messages'][0]['id'] ?? null);
        $this->assertDatabaseHas('wa_message_logs', ['wa_message_id' => 'wamid.AAA1']);
    }

    public function test_duplicate_wamid_is_idempotent(): void
    {
        $payload = $this->messagePayload('wamid.DUP1');

        $first = $this->postSigned($payload);
        $second = $this->postSigned($payload);

        $first->assertStatus(204);
        $second->assertStatus(200); // duplicate path returns 200 ok JSON
        $this->assertSame(1, $this->handleCalls, 'Second delivery with same wamid must be skipped');
        $this->assertSame(1, WAMessageLog::where('wa_message_id', 'wamid.DUP1')->count());
    }

    public function test_status_only_payload_does_not_dispatch_flow(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111'],
                        'statuses' => [[
                            'id' => 'wamid.STAT1',
                            'status' => 'delivered',
                            'timestamp' => (string) now()->timestamp,
                            'recipient_id' => '96599887766',
                        ]],
                    ],
                ]],
            ]],
        ];

        $resp = $this->postSigned($payload);

        $resp->assertStatus(200);
        $this->assertSame(0, $this->handleCalls, 'Delivery-status payloads must not trigger flow handling');
        // Status events are still persisted (so we have audit/debug history).
        $this->assertDatabaseHas('wa_message_logs', ['wa_message_id' => 'wamid.STAT1', 'status' => 'delivered']);
    }

    public function test_empty_messages_array_does_not_dispatch_flow(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '111'],
                        // no messages, no statuses
                    ],
                ]],
            ]],
        ];

        $resp = $this->postSigned($payload);

        $resp->assertStatus(200);
        $this->assertSame(0, $this->handleCalls);
    }
}

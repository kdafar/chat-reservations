<?php

namespace Tests\Feature;

use App\Http\Controllers\V2\WaModuleController;
use App\Wa\Console\Commands\ProcessScheduledCampaigns;
use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Workflow tests for the WhatsApp module — these assert PRODUCT BEHAVIOUR
 * (Meta validation rules, webhook direction, scheduled dispatch), not just
 * that pages render. They run against the real `wa` MySQL connection (the
 * module's own), so they self-skip if that connection isn't reachable.
 */
class WaModuleWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // phpunit forces DB_DATABASE=:memory:, which leaks into the wa connection
        // fallback. Point the module connection at the real MySQL DB for the test.
        config([
            'database.connections.wa.driver' => 'mysql',
            'database.connections.wa.database' => env('WA_DB_DATABASE', 'barfres'),
            'database.connections.wa.prefix' => 'wam_',
        ]);
        \DB::purge('wa');
        try {
            \DB::connection('wa')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('wa MySQL connection unavailable: '.$e->getMessage());
        }
    }

    /** A WhatsAppService whose Meta dup-check always returns false. */
    private function fakeWa(): WhatsAppService
    {
        return new class extends WhatsAppService
        {
            public function __construct() {}

            public function doesTemplateExist(string $name): bool
            {
                return false;
            }
        };
    }

    private function validate(array $data): array
    {
        $c = new WaModuleController;
        $ref = new \ReflectionMethod($c, 'validateTemplate');
        $ref->setAccessible(true);

        return $ref->invoke($c, Request::create('/x', 'POST', $data), null, $this->fakeWa());
    }

    private function goodTemplate(array $override = []): array
    {
        return array_merge([
            'name' => 'welcome msg', 'category' => 'MARKETING', 'language' => 'en',
            'body' => 'Hi {{1}}, welcome!', 'body_examples' => ['Sara'], 'header_type' => 'NONE', 'buttons' => [],
        ], $override);
    }

    public function test_template_name_is_normalized_with_lang_suffix(): void
    {
        $out = $this->validate($this->goodTemplate(['name' => 'Welcome Message', 'language' => 'ar']));
        $this->assertSame('welcome_message_ar', $out['name']);
    }

    public function test_valid_template_passes_and_carries_body_example(): void
    {
        $out = $this->validate($this->goodTemplate());
        $body = collect($out['components'])->firstWhere('type', 'BODY');
        $this->assertSame(['Sara'], $body['example']['body_text'][0] ?? null);
    }

    /** @dataProvider invalidTemplates */
    public function test_template_rules_reject_bad_input(array $override, string $needle): void
    {
        try {
            $this->validate($this->goodTemplate($override));
            $this->fail("Expected validation failure for: {$needle}");
        } catch (ValidationException $e) {
            $msg = implode(' ', \Illuminate\Support\Arr::flatten($e->errors()));
            $this->assertStringContainsStringIgnoringCase($needle, $msg);
        }
    }

    public static function invalidTemplates(): array
    {
        return [
            'body starts with variable' => [['body' => '{{1}} hi', 'body_examples' => ['x']], 'starting with a variable'],
            'non-sequential variables' => [['body' => 'Hi {{1}} and {{3}}.', 'body_examples' => ['a', 'b']], 'sequential'],
            'missing body samples' => [['body_examples' => []], 'sample value for each'],
            'media header without sample' => [['header_type' => 'IMAGE'], 'sample IMAGE is required'],
            'mixed button types' => [['buttons' => [['type' => 'QUICK_REPLY', 'text' => 'Y'], ['type' => 'URL', 'text' => 'Go', 'url' => 'https://x.com']]], 'mix Quick Reply'],
            'invalid url button' => [['buttons' => [['type' => 'URL', 'text' => 'Go', 'url' => 'nope']]], 'valid https'],
            'footer with variable' => [['footer_text' => 'Bye {{1}}'], 'cannot contain variables'],
        ];
    }

    public function test_scheduled_campaign_dispatches_when_due(): void
    {
        Queue::fake();
        $cmp = PromotionalCampaign::create(['name' => 'WF Sched', 'status' => 'scheduled', 'default_locale' => 'en', 'scheduled_at' => now()->subMinutes(2)]);
        PromotionalCampaignRecipient::create(['promotional_campaign_id' => $cmp->id, 'msisdn' => '+96599000123', 'status' => 'pending', 'locale' => 'en']);

        $this->artisan('wa:campaigns:process-scheduled')->assertSuccessful();

        $cmp->refresh();
        $this->assertSame('sending', $cmp->status);
        Queue::assertPushed(SendPromotionalCampaignMessage::class, 1);

        $cmp->recipients()->delete();
        $cmp->delete();
    }

    public function test_webhook_stores_incoming_message_as_inbound(): void
    {
        $c = new WaModuleController;
        $ensure = new \ReflectionMethod($c, 'ensureCore');
        $ensure->setAccessible(true);
        $number = $ensure->invoke($c);
        if (! $number) {
            $this->markTestSkipped('WhatsApp not configured (no WaNumber).');
        }

        $wamid = 'wamid.TEST_'.uniqid();
        $payload = ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $number->phone_number_id],
            'contacts' => [['wa_id' => '96599000999', 'profile' => ['name' => 'WF Tester']]],
            'messages' => [['from' => '96599000999', 'id' => $wamid, 'type' => 'text', 'timestamp' => (string) now()->timestamp, 'text' => ['body' => 'hello from test']]],
        ]]]]]];

        $this->postJson('/api/wa/webhooks/whatsapp-cloud', $payload)->assertOk();

        $msg = WaMessage::where('meta_message_id', $wamid)->first();
        $this->assertNotNull($msg, 'webhook did not store the message');
        $this->assertSame('inbound', $msg->direction);

        // cleanup
        optional($msg->conversation)->messages()->delete();
        optional($msg->conversation)->delete();
        $msg->delete();
    }
}

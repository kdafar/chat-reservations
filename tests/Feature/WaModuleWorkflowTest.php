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
        $db = env('WA_DB_DATABASE', 'barfres');
        config([
            'database.connections.wa.driver' => 'mysql',
            'database.connections.wa.database' => $db,
            'database.connections.wa.prefix' => 'wam_',
            // default -> the real MySQL too, so auth (users/roles) resolves for
            // actingAs(). Base TestCase uses no RefreshDatabase, so nothing wipes.
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $db,
        ]);
        \DB::purge('wa');
        \DB::purge('mysql');
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

    public function test_campaign_send_blocks_until_template_and_variables_set(): void
    {
        Queue::fake();
        $admin = \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin', 'clinic_admin']))->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user');
        }

        // template with one body variable
        $tpl = \App\Wa\Hub\Models\MessageTemplate::create([
            'name' => 'wf_camp_tpl_en', 'category' => 'MARKETING', 'language' => 'en', 'status' => 'APPROVED',
            'body' => 'Hi {{1}}!', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}!']], 'local_status' => 'published',
        ]);
        $c = PromotionalCampaign::create(['name' => 'WF Camp', 'status' => 'draft', 'default_locale' => 'en']);
        PromotionalCampaignRecipient::create(['promotional_campaign_id' => $c->id, 'msisdn' => '+96599000222', 'status' => 'pending', 'locale' => 'en']);

        // no template selected yet -> blocked
        $this->actingAs($admin)->post(route('v2.wa-module.campaigns.send', $c))->assertSessionHas('flash.type', 'error');
        Queue::assertNotPushed(SendPromotionalCampaignMessage::class);

        // attach template but leave the variable empty -> still blocked
        $c->update(['template_name' => $tpl->name, 'template_details' => ['components' => $tpl->components], 'template_variables' => ['1' => '']]);
        $this->actingAs($admin)->post(route('v2.wa-module.campaigns.send', $c))->assertSessionHas('flash.type', 'error');
        Queue::assertNotPushed(SendPromotionalCampaignMessage::class);

        // fill the variable -> queues
        $c->update(['template_variables' => ['1' => 'Sara']]);
        $this->actingAs($admin)->post(route('v2.wa-module.campaigns.send', $c))->assertSessionHas('flash.type', 'success');
        $c->refresh();
        $this->assertSame('sending', $c->status);
        Queue::assertPushed(SendPromotionalCampaignMessage::class, 1);

        $c->recipients()->delete();
        $c->delete();
        $tpl->delete();
    }

    public function test_campaign_pause_resume_transitions(): void
    {
        $admin = \App\Models\User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin', 'clinic_admin']))->first();
        if (! $admin) {
            $this->markTestSkipped('no admin user');
        }
        Queue::fake();
        $c = PromotionalCampaign::create(['name' => 'WF PR', 'status' => 'sending', 'default_locale' => 'en']);

        $this->actingAs($admin)->post(route('v2.wa-module.campaigns.pause', $c))->assertSessionHas('flash.type', 'success');
        $this->assertSame('paused', $c->fresh()->status);

        $this->actingAs($admin)->post(route('v2.wa-module.campaigns.resume', $c))->assertSessionHas('flash.type', 'success');
        $this->assertSame('sending', $c->fresh()->status);

        $c->delete();
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

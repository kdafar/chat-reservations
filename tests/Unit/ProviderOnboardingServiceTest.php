<?php

namespace Tests\Unit;

use App\Models\Flow;
use App\Models\FlowTemplate;
use App\Models\FlowTrigger;
use App\Models\FlowVersion;
use App\Models\Provider;
use App\Models\ServiceType;
use App\Services\ProviderOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for ProviderOnboardingService (audit follow-up #5).
 *
 * onboard($provider) walks the provider's ServiceType templates and clones
 * each template's latest version into a provider-specific Flow + FlowVersion
 * + FlowTrigger, so the provider can immediately receive WhatsApp traffic.
 *
 * If this method regresses, new providers go live with no flows wired up
 * and inbound messages silently fall through.
 */
class ProviderOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProviderOnboardingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ProviderOnboardingService::class);
    }

    /** Seed a complete service-type → template → version chain. */
    private function seedTemplateChain(): array
    {
        $service = new ServiceType;
        $service->forceFill([
            'slug' => 'clinic-'.uniqid(),
            'name' => 'Clinic',
            'name_en' => 'Clinic',
            'is_active' => true,
        ])->save();

        $template = FlowTemplate::create([
            'service_type_id' => $service->id,
            'slug' => 'booking-'.uniqid(),
            'name' => 'Booking Flow',
            'description' => 'Test template',
        ]);

        // Template version uses a high version number so the cloned
        // provider-side version (which starts at version=1) does not collide
        // on the (flow_template_id, version) unique index.
        $version = FlowVersion::create([
            'flow_template_id' => $template->id,
            'service_type_id' => $service->id,
            'is_template' => true,
            'status' => 'published',
            'version' => 100,
            'name' => 'template-v100',
            'definition' => ['screens' => ['WELCOME', 'CONFIRM']],
            'schema_json' => ['v' => '1.0'],
            'components_json' => ['screens' => ['WELCOME']],
        ]);

        $template->update(['latest_version_id' => $version->id]);

        $provider = Provider::create([
            'service_type_id' => $service->id,
            'name' => 'Test Clinic',
            'slug' => 'test-clinic-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'auth_type' => 'none',
        ]);

        return compact('service', 'template', 'version', 'provider');
    }

    public function test_onboard_clones_flow_version_for_provider(): void
    {
        ['provider' => $provider, 'template' => $template] = $this->seedTemplateChain();

        $this->svc->onboard($provider);

        // A Flow was created for the provider, with the template slug as trigger.
        $flow = Flow::where('provider_id', $provider->id)->first();
        $this->assertNotNull($flow);
        $this->assertSame($template->slug, $flow->trigger_keyword);

        // A new (non-template) FlowVersion exists for that flow.
        $newVer = $flow->versions()->first();
        $this->assertNotNull($newVer);
        $this->assertFalse((bool) $newVer->is_template);
        $this->assertSame((int) $provider->id, (int) $newVer->provider_id);
        $this->assertSame('published', $newVer->status);
    }

    public function test_onboard_creates_flow_trigger(): void
    {
        ['provider' => $provider, 'template' => $template] = $this->seedTemplateChain();

        $this->svc->onboard($provider);

        $trigger = FlowTrigger::where('provider_id', $provider->id)
            ->where('keyword', $template->slug)
            ->first();

        $this->assertNotNull($trigger, 'FlowTrigger should be created for the provider');
        $this->assertTrue((bool) $trigger->is_active);
        $this->assertTrue((bool) $trigger->use_latest_published);
    }

    public function test_onboard_is_idempotent(): void
    {
        ['provider' => $provider] = $this->seedTemplateChain();

        $this->svc->onboard($provider);
        $flowCount = Flow::where('provider_id', $provider->id)->count();
        $versionCount = FlowVersion::where('provider_id', $provider->id)->count();
        $triggerCount = FlowTrigger::where('provider_id', $provider->id)->count();

        // Re-onboarding must NOT duplicate
        $this->svc->onboard($provider);

        $this->assertSame($flowCount, Flow::where('provider_id', $provider->id)->count());
        $this->assertSame($versionCount, FlowVersion::where('provider_id', $provider->id)->count());
        $this->assertSame($triggerCount, FlowTrigger::where('provider_id', $provider->id)->count());
    }

    public function test_onboard_with_no_templates_no_ops(): void
    {
        $service = new ServiceType;
        $service->forceFill([
            'slug' => 'no-templates-'.uniqid(),
            'name' => 'Empty Service',
            'name_en' => 'Empty Service',
            'is_active' => true,
        ])->save();
        $provider = Provider::create([
            'service_type_id' => $service->id,
            'name' => 'No Flows',
            'slug' => 'no-flows-'.uniqid(),
            'status' => 'active',
            'is_active' => true,
            'auth_type' => 'none',
        ]);

        $this->svc->onboard($provider);

        $this->assertSame(0, Flow::where('provider_id', $provider->id)->count());
    }
}

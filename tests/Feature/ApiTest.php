<?php

namespace Tests\Feature;

use App\Jobs\ScanLink;
use App\Jobs\SendWebhook;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Safety\ThreatScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function proUser(): User
    {
        return User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);
    }

    public function test_token_creation_requires_an_api_plan(): void
    {
        $free = User::factory()->create();
        $this->actingAs($free)->post('/api-tokens', ['name' => 'Test'])->assertSessionHas('error');
        $this->assertSame(0, $free->tokens()->count());

        $pro = $this->proUser();
        $this->actingAs($pro)->post('/api-tokens', ['name' => 'Test'])->assertSessionHas('plain_token');
        $this->assertSame(1, $pro->tokens()->count());
    }

    public function test_api_creates_and_lists_links_with_a_token(): void
    {
        $pro = $this->proUser();
        $token = $pro->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/links', ['long_url' => 'https://example.com/x'])
            ->assertCreated()
            ->assertJsonPath('data.destination', 'https://example.com/x');

        $this->assertDatabaseHas('links', ['user_id' => $pro->id, 'long_url' => 'https://example.com/x']);

        $this->withToken($token)->getJson('/api/v1/links')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
    }

    public function test_api_is_gated_by_plan_feature(): void
    {
        $free = User::factory()->create();
        $token = $free->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/links')->assertForbidden();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/links')->assertUnauthorized();
    }

    public function test_api_resource_route_names_do_not_shadow_web_routes(): void
    {
        // The API resource is namespaced (api.v1.*) so route('links.index') keeps
        // resolving to the web Links page, not /api/v1/links (regression guard).
        $this->assertStringEndsWith('/links', route('links.index'));
        $this->assertStringContainsString('/api/v1/links', route('api.v1.links.index'));
    }

    public function test_creating_a_link_dispatches_a_subscribed_webhook(): void
    {
        Bus::fake([SendWebhook::class]);

        $user = User::factory()->create();
        $user->webhooks()->create(['url' => 'https://hook.test/x', 'events' => ['link.created'], 'secret' => 's', 'is_active' => true]);

        $this->actingAs($user)->post('/links', ['long_url' => 'https://example.com'])->assertRedirect(route('links.index'));

        Bus::assertDispatched(SendWebhook::class, fn ($job) => $job->event === 'link.created');
    }

    public function test_send_webhook_posts_a_signed_payload(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $webhook = $user->webhooks()->create(['url' => 'https://hook.test/x', 'events' => ['link.created'], 'secret' => 'topsecret', 'is_active' => true]);

        (new SendWebhook($webhook->id, 'link.created', ['id' => 1]))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://hook.test/x'
            && $request->hasHeader('X-LinkForge-Signature')
            && $request->hasHeader('X-LinkForge-Event', 'link.created'));
    }

    private function makeLink(User $user, array $attrs = []): Link
    {
        return $user->links()->create(array_merge([
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'a'.Str::random(6),
            'long_url' => 'https://example.com',
            'type' => 'direct',
            'safety_status' => 'safe',
        ], $attrs));
    }

    public function test_clicking_a_link_dispatches_a_subscribed_webhook(): void
    {
        Bus::fake([SendWebhook::class]);

        $user = User::factory()->create();
        $user->webhooks()->create(['url' => 'https://hook.test/c', 'events' => ['link.clicked'], 'secret' => 's', 'is_active' => true]);
        $this->makeLink($user, ['alias' => 'clk']);

        $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
        $this->get('/clk', ['User-Agent' => $chrome])->assertRedirect('https://example.com');

        Bus::assertDispatched(SendWebhook::class, fn ($job) => $job->event === 'link.clicked');
    }

    public function test_bot_clicks_do_not_dispatch_a_webhook(): void
    {
        Bus::fake([SendWebhook::class]);

        $user = User::factory()->create();
        $user->webhooks()->create(['url' => 'https://hook.test/c', 'events' => ['link.clicked'], 'secret' => 's', 'is_active' => true]);
        $this->makeLink($user, ['alias' => 'bot']);

        $this->get('/bot', ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->assertRedirect();

        Bus::assertNotDispatched(SendWebhook::class);
    }

    public function test_flagging_a_link_dispatches_a_subscribed_webhook(): void
    {
        Bus::fake([SendWebhook::class]);

        $user = User::factory()->create();
        $user->webhooks()->create(['url' => 'https://hook.test/f', 'events' => ['link.flagged'], 'secret' => 's', 'is_active' => true]);
        $link = $this->makeLink($user, ['safety_status' => 'safe']);

        $scanner = \Mockery::mock(ThreatScanner::class);
        $scanner->shouldReceive('scan')->andReturn(['status' => 'blocked', 'score' => 100, 'scans' => []]);

        (new ScanLink($link->id))->handle($scanner);

        Bus::assertDispatched(SendWebhook::class, fn ($job) => $job->event === 'link.flagged');
    }

    public function test_flagged_webhook_does_not_refire_when_already_flagged(): void
    {
        Bus::fake([SendWebhook::class]);

        $user = User::factory()->create();
        $user->webhooks()->create(['url' => 'https://hook.test/f', 'events' => ['link.flagged'], 'secret' => 's', 'is_active' => true]);
        $link = $this->makeLink($user, ['safety_status' => 'blocked']); // already flagged

        $scanner = \Mockery::mock(ThreatScanner::class);
        $scanner->shouldReceive('scan')->andReturn(['status' => 'blocked', 'score' => 100, 'scans' => []]);

        (new ScanLink($link->id))->handle($scanner);

        Bus::assertNotDispatched(SendWebhook::class);
    }
}

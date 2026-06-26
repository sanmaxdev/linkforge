<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Setting;
use App\Models\User;
use App\Providers\SettingsServiceProvider;
use App\Services\Ai\ClaudeClient;
use App\Services\Ai\NlAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    /** Mock the Anthropic Messages API with the given JSON-text content block(s). */
    private function fakeClaude(array $texts): void
    {
        $sequence = Http::fakeSequence('api.anthropic.com/*');
        foreach ($texts as $text) {
            $sequence->push(['content' => [['type' => 'text', 'text' => $text]]], 200);
        }
    }

    private function enableAi(): void
    {
        // These tests exercise the feature logic over the Anthropic provider, so pin it.
        // The default provider is now OpenRouter (see the dedicated routing test below).
        config([
            'linkforge.ai.provider' => 'anthropic',
            'linkforge.ai.key' => 'sk-test',
            'linkforge.ai.model' => 'claude-opus-4-8',
        ]);
    }

    public function test_claude_client_is_disabled_without_a_key(): void
    {
        config(['linkforge.ai.provider' => 'anthropic', 'linkforge.ai.key' => null]);
        $this->assertFalse(app(ClaudeClient::class)->enabled());

        config(['linkforge.ai.key' => 'sk-test']);
        $this->assertTrue(app(ClaudeClient::class)->enabled());
    }

    public function test_openrouter_is_the_default_provider_and_routes_correctly(): void
    {
        // Fresh config defaults to OpenRouter on a cheap model.
        $this->assertSame('openrouter', app(ClaudeClient::class)->provider());
        $this->assertSame('openai/gpt-4o-mini', config('linkforge.ai.openrouter.model'));

        config(['linkforge.ai.openrouter.key' => 'sk-or-test']);
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['aliases' => ['spring-sale', 'launch-2026']])]]],
        ], 200)]);

        $user = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/spring'])
            ->assertOk()
            ->assertJson(['suggestions' => ['spring-sale', 'launch-2026']]);

        // The request went to OpenRouter (the cheap default), never Anthropic.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'openrouter.ai'));
    }

    public function test_ai_endpoints_are_unavailable_without_a_key(): void
    {
        config(['linkforge.ai.key' => null]);
        Http::fake();

        $user = User::factory()->create(['ai_credits' => 10]);

        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/sale'])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_alias_suggestion_returns_available_slugs_and_charges_a_credit(): void
    {
        $this->enableAi();
        $this->fakeClaude([json_encode(['aliases' => ['summer-sale', 'spring-deal', 'launch-2026']])]);

        $user = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/summer'])
            ->assertOk()
            ->assertJson(['suggestions' => ['summer-sale', 'spring-deal', 'launch-2026'], 'credits' => 4]);

        $this->assertSame(4, (int) $user->fresh()->ai_credits);
    }

    public function test_alias_suggestion_drops_taken_slugs(): void
    {
        $this->enableAi();
        $domain = Domain::query()->first();

        // Occupy one of the candidate slugs.
        $user = User::factory()->create(['ai_credits' => 5]);
        $user->links()->create(['domain_id' => $domain->id, 'alias' => 'taken-slug', 'long_url' => 'https://example.com']);

        $this->fakeClaude([json_encode(['aliases' => ['taken-slug', 'fresh-slug']])]);

        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/x'])
            ->assertOk()
            ->assertJson(['suggestions' => ['fresh-slug']]);
    }

    public function test_out_of_credits_is_blocked_and_no_api_call_is_made(): void
    {
        $this->enableAi();
        Http::fake();

        $user = User::factory()->create(['ai_credits' => 0]);

        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/x'])
            ->assertStatus(402);

        Http::assertNothingSent();
    }

    public function test_ask_maps_question_to_an_allowlisted_query_over_rollups(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'q1', 'long_url' => 'https://example.com']);

        // Real rollup row the allowlisted query must read.
        DB::table('stat_daily')->insert([
            'link_id' => $link->id, 'day' => now()->toDateString(),
            'clicks' => 42, 'uniques' => 30, 'bots' => 2,
        ]);

        // 1) intent (structured), 2) narration (free text).
        $this->fakeClaude([
            json_encode(['metric' => 'clicks', 'dimension' => 'none', 'range_days' => 7, 'top_n' => 8, 'understood' => true]),
            'You had 42 clicks in the last 7 days.',
        ]);

        $response = $this->actingAs($user)->postJson('/ai/ask', ['question' => 'how many clicks last 7 days?'])
            ->assertOk()
            ->assertJsonPath('data.value', 42)
            ->assertJsonPath('intent.metric', 'clicks');

        // The model only chose from the allowlist; the figure came from the rollup table.
        $this->assertContains($response->json('intent.dimension'), NlAnalytics::DIMENSIONS);
        $this->assertSame(4, (int) $user->fresh()->ai_credits);

        // The narration request carried only the computed aggregate to the model.
        Http::assertSent(function ($request) {
            $content = collect($request->data()['messages'] ?? [])->pluck('content')->implode(' ');

            return str_contains($content, 'value') && str_contains($content, '42');
        });
        // No request ever carried SQL: the model only chose from the allowlist.
        Http::assertNotSent(fn ($request) => stripos($request->body(), 'select ') !== false);
    }

    public function test_ask_refunds_credit_when_question_is_not_understood(): void
    {
        $this->enableAi();
        $this->fakeClaude([
            json_encode(['metric' => 'clicks', 'dimension' => 'none', 'range_days' => 30, 'top_n' => 8, 'understood' => false]),
        ]);

        $user = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($user)->postJson('/ai/ask', ['question' => 'what is the meaning of life?'])
            ->assertOk()
            ->assertJson(['understood' => false]);

        $this->assertSame(5, (int) $user->fresh()->ai_credits);
    }

    public function test_ask_ranks_top_links(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $a = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'aaa', 'title' => 'Big Sale', 'long_url' => 'https://example.com/a']);
        $b = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'bbb', 'long_url' => 'https://example.com/b']);
        DB::table('stat_daily')->insert([
            ['link_id' => $a->id, 'day' => now()->toDateString(), 'clicks' => 50, 'uniques' => 40, 'bots' => 0],
            ['link_id' => $b->id, 'day' => now()->toDateString(), 'clicks' => 12, 'uniques' => 9, 'bots' => 0],
        ]);
        $this->fakeClaude([
            json_encode(['metric' => 'clicks', 'dimension' => 'link', 'range_days' => 30, 'top_n' => 8, 'compare' => false, 'understood' => true]),
            'Your top link is Big Sale with 50 clicks.',
        ]);

        $res = $this->actingAs($user)->postJson('/ai/ask', ['question' => 'which links got the most clicks?'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'links')
            ->assertJsonPath('data.rows.0.label', 'Big Sale') // title preferred over alias
            ->assertJsonPath('data.rows.0.clicks', 50);
        $this->assertSame('bbb', $res->json('data.rows.1.label')); // no title falls back to alias
    }

    public function test_ask_compares_to_the_previous_period(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'cmp', 'long_url' => 'https://example.com']);
        // 30 clicks in the current 7-day window; 10 in the preceding one (8 days ago).
        DB::table('stat_daily')->insert([
            ['link_id' => $link->id, 'day' => now()->toDateString(), 'clicks' => 30, 'uniques' => 20, 'bots' => 0],
            ['link_id' => $link->id, 'day' => now()->subDays(8)->toDateString(), 'clicks' => 10, 'uniques' => 8, 'bots' => 0],
        ]);
        $this->fakeClaude([
            json_encode(['metric' => 'clicks', 'dimension' => 'none', 'range_days' => 7, 'top_n' => 8, 'compare' => true, 'understood' => true]),
            'Clicks are up versus last week.',
        ]);

        $this->actingAs($user)->postJson('/ai/ask', ['question' => 'clicks this week vs last week'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'total')
            ->assertJsonPath('data.value', 30)
            ->assertJsonPath('data.previous', 10)
            ->assertJsonPath('data.change_pct', 200);
    }

    public function test_ask_supports_the_city_dimension(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'cty', 'long_url' => 'https://example.com']);
        DB::table('stat_dimension')->insert([
            ['link_id' => $link->id, 'day' => now()->toDateString(), 'dimension' => 'city', 'label' => 'London', 'clicks' => 20],
            ['link_id' => $link->id, 'day' => now()->toDateString(), 'dimension' => 'city', 'label' => 'Paris', 'clicks' => 8],
        ]);
        $this->fakeClaude([
            json_encode(['metric' => 'clicks', 'dimension' => 'city', 'range_days' => 30, 'top_n' => 8, 'compare' => false, 'understood' => true]),
            'London leads with 20 clicks.',
        ]);

        $this->actingAs($user)->postJson('/ai/ask', ['question' => 'top cities last 30 days'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'breakdown')
            ->assertJsonPath('data.dimension', 'city')
            ->assertJsonPath('data.rows.0.label', 'London')
            ->assertJsonPath('data.rows.0.clicks', 20);
    }

    public function test_ai_title_writes_a_title_and_charges(): void
    {
        $this->enableAi();
        $this->fakeClaude([json_encode(['title' => 'Spring Sale 2026', 'description' => 'Shop the spring sale now.'])]);
        $user = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($user)->postJson('/ai/title', ['long_url' => 'https://example.com/spring-sale'])
            ->assertOk()
            ->assertJson(['title' => 'Spring Sale 2026', 'description' => 'Shop the spring sale now.', 'credits' => 4]);
        $this->assertSame(4, (int) $user->fresh()->ai_credits);
    }

    public function test_link_insight_summarises_a_single_link(): void
    {
        $this->enableAi();
        $this->fakeClaude(['This link is up this week, led by the US. Keep sharing it.']);
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'ins', 'long_url' => 'https://example.com']);
        DB::table('stat_daily')->insert(['link_id' => $link->id, 'day' => now()->toDateString(), 'clicks' => 9, 'uniques' => 7, 'bots' => 0]);

        $this->actingAs($user)->postJson('/ai/links/'.$link->id.'/insight')
            ->assertOk()
            ->assertJsonPath('summary', 'This link is up this week, led by the US. Keep sharing it.')
            ->assertJsonPath('credits', 4);
    }

    public function test_link_insight_rejects_another_users_link_without_charging(): void
    {
        $this->enableAi();
        Http::fake();
        $owner = User::factory()->create();
        $domain = Domain::query()->first();
        $link = $owner->links()->create(['domain_id' => $domain->id, 'alias' => 'mine', 'long_url' => 'https://example.com']);
        $intruder = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($intruder)->postJson('/ai/links/'.$link->id.'/insight')->assertStatus(403);

        Http::assertNothingSent();
        $this->assertSame(5, (int) $intruder->fresh()->ai_credits);
    }

    public function test_bio_copy_writes_profile_copy(): void
    {
        $this->enableAi();
        $this->fakeClaude([json_encode(['display_name' => 'Maya Travels', 'headline' => 'Bali-based travel photographer', 'bio' => 'Capturing island light, one frame at a time.'])]);
        $user = User::factory()->create(['ai_credits' => 5]);

        $this->actingAs($user)->postJson('/ai/bio-copy', ['topic' => 'travel photographer in Bali'])
            ->assertOk()
            ->assertJson(['display_name' => 'Maya Travels', 'headline' => 'Bali-based travel photographer', 'credits' => 4]);
    }

    public function test_ai_helpers_render_on_their_pages_when_enabled(): void
    {
        $this->enableAi();
        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create(['domain_id' => $domain->id, 'alias' => 'pg', 'long_url' => 'https://example.com']);

        // The AI-gated blocks only render when AI is enabled; assert each one is present.
        $this->actingAs($user)->get(route('links.create'))->assertOk()->assertSee('Write title with AI');
        $this->actingAs($user)->get(route('bio.create'))->assertOk()->assertSee('Write with AI');
        $this->actingAs($user)->get(route('links.stats', $link))->assertOk()->assertSee('AI performance summary');
    }

    public function test_weekly_insights_command_generates_and_stores_an_insight(): void
    {
        $this->enableAi();
        $this->fakeClaude(['Clicks are up this week. Keep sharing your top link.']);

        $user = User::factory()->create(['ai_credits' => 5]);
        $domain = Domain::query()->first();
        $link = $user->links()->create([
            'domain_id' => $domain->id, 'alias' => 'wk', 'long_url' => 'https://example.com',
            'last_click_at' => now()->subDay(),
        ]);
        DB::table('stat_daily')->insert([
            'link_id' => $link->id, 'day' => now()->toDateString(),
            'clicks' => 12, 'uniques' => 9, 'bots' => 0,
        ]);

        $this->artisan('ai:weekly-insights')->assertSuccessful();

        $insight = data_get($user->fresh()->settings, 'weekly_insight');
        $this->assertNotNull($insight);
        $this->assertSame('Clicks are up this week. Keep sharing your top link.', $insight['text']);
        // It is metered: the insight charged one AI credit (5 -> 4).
        $this->assertSame(4, (int) $user->fresh()->ai_credits);
    }

    public function test_weekly_insight_skips_accounts_without_credits(): void
    {
        $this->enableAi();
        Http::fake();

        $user = User::factory()->create(['ai_credits' => 0]);
        $domain = Domain::query()->first();
        $link = $user->links()->create([
            'domain_id' => $domain->id, 'alias' => 'wk0', 'long_url' => 'https://example.com',
            'last_click_at' => now()->subDay(),
        ]);
        DB::table('stat_daily')->insert([
            'link_id' => $link->id, 'day' => now()->toDateString(), 'clicks' => 7, 'uniques' => 5, 'bots' => 0,
        ]);

        $this->artisan('ai:weekly-insights')->assertSuccessful();

        $this->assertNull(data_get($user->fresh()->settings, 'weekly_insight'));
        Http::assertNothingSent(); // no model call for an account out of credits
    }

    public function test_weekly_insights_command_is_a_noop_without_a_key(): void
    {
        config(['linkforge.ai.provider' => 'anthropic', 'linkforge.ai.key' => null]);
        Http::fake();

        $this->artisan('ai:weekly-insights')->assertSuccessful();

        Http::assertNothingSent();
    }

    // -- OpenRouter provider ----------------------------------------------

    private function enableOpenRouter(string $model = 'openai/gpt-4o'): void
    {
        config(['linkforge.ai.provider' => 'openrouter', 'linkforge.ai.openrouter.key' => 'sk-or-x', 'linkforge.ai.openrouter.model' => $model]);
    }

    public function test_openrouter_provider_reports_enabled_and_its_model(): void
    {
        $this->enableOpenRouter('google/gemini-2.0-flash-001');
        $client = app(ClaudeClient::class);

        $this->assertTrue($client->enabled());
        $this->assertSame('openrouter', $client->provider());
        $this->assertSame('google/gemini-2.0-flash-001', $client->model());

        config(['linkforge.ai.openrouter.key' => null]);
        $this->assertFalse(app(ClaudeClient::class)->enabled());
    }

    public function test_structured_call_routes_to_openrouter_chat_completions(): void
    {
        $this->enableOpenRouter('openai/gpt-4o');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"aliases":["x","y"]}']]]])]);

        $out = app(ClaudeClient::class)->structured('system', 'prompt', ['type' => 'object']);
        $this->assertSame(['x', 'y'], $out['aliases']);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'openrouter.ai/api/v1/chat/completions')
            && ($req->data()['model'] ?? null) === 'openai/gpt-4o'
            && $req->hasHeader('Authorization', 'Bearer sk-or-x'));
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'anthropic.com'));
    }

    public function test_alias_suggestion_works_over_openrouter(): void
    {
        $this->enableOpenRouter();
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['aliases' => ['summer-sale', 'spring-deal']])]]]])]);

        $user = User::factory()->create(['ai_credits' => 5]);
        $this->actingAs($user)->postJson('/ai/alias', ['long_url' => 'https://example.com/x'])
            ->assertOk()
            ->assertJson(['suggestions' => ['summer-sale', 'spring-deal'], 'credits' => 4]);
    }

    public function test_admin_can_switch_ai_provider_to_openrouter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'section' => 'ai', 'ai_provider' => 'openrouter', 'ai_model' => 'claude-opus-4-8',
            'openrouter_model' => 'openai/gpt-4o', 'openrouter_key' => 'sk-or-x',
            'ai_cost_alias' => 1, 'ai_cost_ask' => 1, 'ai_cost_insight' => 1,
        ])->assertRedirect();

        $this->assertSame('openrouter', Setting::get('ai_provider'));
        $this->assertSame('openai/gpt-4o', Setting::get('openrouter_model'));

        (new SettingsServiceProvider($this->app))->boot();
        $this->assertSame('openrouter', config('linkforge.ai.provider'));
        $this->assertSame('openai/gpt-4o', config('linkforge.ai.openrouter.model'));
        $this->assertSame('sk-or-x', config('linkforge.ai.openrouter.key'));
    }
}

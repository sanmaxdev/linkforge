<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function makeLink(User $user, array $attrs = []): Link
    {
        return Link::create(array_merge([
            'user_id' => $user->id,
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'go'.$user->links()->count(),
            'long_url' => 'https://example.com/dest',
            'type' => 'direct',
            'safety_status' => 'safe',
        ], $attrs));
    }

    public function test_user_can_create_a_campaign(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/campaigns', ['name' => 'Spring sale', 'color' => 'blue'])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', ['user_id' => $user->id, 'name' => 'Spring sale', 'color' => 'blue']);
    }

    public function test_link_can_be_assigned_to_a_campaign_and_tagged(): void
    {
        $user = User::factory()->create();
        $campaign = $user->campaigns()->create(['name' => 'Launch']);

        $this->actingAs($user)->post('/links', [
            'long_url' => 'https://example.com/page',
            'alias' => 'promo',
            'campaign_id' => $campaign->id,
            'tags' => 'Sale, SALE, promo!!',
        ])->assertRedirect(route('links.index'));

        $link = $user->links()->where('alias', 'promo')->firstOrFail();
        $this->assertSame($campaign->id, $link->campaign_id);
        $this->assertSame(['sale', 'promo'], $link->tags); // normalised + deduped
    }

    public function test_links_can_be_filtered_by_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = $user->campaigns()->create(['name' => 'Launch']);
        $this->makeLink($user, ['alias' => 'incamp', 'campaign_id' => $campaign->id]);
        $this->makeLink($user, ['alias' => 'loose']);

        $res = $this->actingAs($user)->get(route('links.index', ['campaign' => $campaign->id]));
        $res->assertSee('incamp')->assertDontSee('>loose<', false);
    }

    public function test_links_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $this->makeLink($user, ['alias' => 'tagged', 'tags' => ['sale']]);
        $this->makeLink($user, ['alias' => 'plain']);

        $res = $this->actingAs($user)->get(route('links.index', ['tag' => 'sale']));
        $res->assertSee('tagged')->assertDontSee('>plain<', false);
    }

    public function test_a_user_cannot_assign_someone_elses_campaign(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $campaign = $owner->campaigns()->create(['name' => 'Theirs']);

        $this->actingAs($other)->post('/links', [
            'long_url' => 'https://example.com',
            'alias' => 'mine',
            'campaign_id' => $campaign->id,
        ])->assertRedirect();

        $this->assertNull($other->links()->where('alias', 'mine')->value('campaign_id'));
    }

    public function test_deleting_a_campaign_keeps_its_links(): void
    {
        $user = User::factory()->create();
        $campaign = $user->campaigns()->create(['name' => 'Temp']);
        $link = $this->makeLink($user, ['alias' => 'keepme', 'campaign_id' => $campaign->id]);

        $this->actingAs($user)->delete(route('campaigns.destroy', $campaign))->assertRedirect();

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'campaign_id' => null]);
    }

    public function test_a_user_cannot_modify_another_users_campaign(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $campaign = $owner->campaigns()->create(['name' => 'Theirs']);

        $this->actingAs($other)->put(route('campaigns.update', $campaign), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->delete(route('campaigns.destroy', $campaign))->assertForbidden();
    }

    public function test_campaign_analytics_and_index_pages_load(): void
    {
        $user = User::factory()->create();
        $campaign = $user->campaigns()->create(['name' => 'Launch']);
        $this->makeLink($user, ['campaign_id' => $campaign->id]);

        $this->actingAs($user)->get(route('campaigns.index'))->assertOk()->assertSee('Launch');
        $this->actingAs($user)->get(route('campaigns.stats', $campaign))->assertOk();
    }
}

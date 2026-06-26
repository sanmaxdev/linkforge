<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixelTest extends TestCase
{
    use RefreshDatabase;

    /** Every supported provider -> a distinctive marker from its official snippet. */
    private const PROVIDERS = [
        'facebook' => 'fbq(',
        'google' => 'gtag(',
        'tiktok' => 'ttq.load',
        'linkedin' => '_linkedin_partner_id',
        'twitter' => 'twq(',
        'pinterest' => 'pintrk(',
        'quora' => 'qp(',
        'bing' => 'bat.bing.com',
        'snapchat' => 'snaptr(',
        'reddit' => 'rdt(',
        'gtm' => 'gtm.js',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_every_supported_provider_renders_its_snippet_on_the_interstitial(): void
    {
        $user = User::factory()->create();
        $domainId = Domain::where('is_default', true)->value('id');

        foreach (self::PROVIDERS as $provider => $marker) {
            $pixel = $user->pixels()->create(['provider' => $provider, 'pixel_id' => 'PX-'.$provider, 'name' => $provider]);
            $link = Link::create([
                'user_id' => $user->id, 'domain_id' => $domainId, 'alias' => 'px'.$provider,
                'long_url' => 'https://example.com/dest', 'type' => 'direct', 'safety_status' => 'safe',
            ]);
            $link->pixels()->attach($pixel->id);

            // A link with a pixel renders the interstitial (pixels need an HTML page to fire on).
            $this->get('/px'.$provider)
                ->assertOk()
                ->assertSee($marker, false)
                ->assertSee('PX-'.$provider, false); // the operator's pixel id is injected
        }
    }

    public function test_pixel_provider_validation_matches_the_supported_set(): void
    {
        // The create form, the controller's validation, and the render partial must agree.
        $user = User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        $this->actingAs($user)->post(route('pixels.store'), [
            'provider' => 'not-a-provider', 'pixel_id' => 'X',
        ])->assertSessionHasErrors('provider');

        foreach (array_keys(self::PROVIDERS) as $provider) {
            $this->actingAs($user)->post(route('pixels.store'), ['provider' => $provider, 'pixel_id' => 'ID-'.$provider])
                ->assertSessionDoesntHaveErrors('provider');
        }

        $this->assertSame(count(self::PROVIDERS), $user->pixels()->count());
    }
}

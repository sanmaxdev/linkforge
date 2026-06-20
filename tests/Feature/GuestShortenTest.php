<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestShortenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_guest_can_shorten_a_url(): void
    {
        $this->postJson('/shorten', ['long_url' => 'https://example.com/very/long/path'])
            ->assertOk()
            ->assertJsonStructure(['short_url', 'long_url']);

        $guest = User::where('email', 'guest@system.local')->firstOrFail();
        $this->assertSame(1, $guest->links()->count());
    }

    public function test_guest_shorten_can_be_disabled(): void
    {
        Setting::put('guest_shorten', '0');

        $this->postJson('/shorten', ['long_url' => 'https://example.com'])->assertStatus(403);
        $this->assertSame(0, User::where('email', 'guest@system.local')->firstOrFail()->links()->count());
    }

    public function test_guest_shorten_rejects_an_invalid_url(): void
    {
        $this->postJson('/shorten', ['long_url' => 'not-a-real-url'])->assertStatus(422);
    }

    public function test_landing_page_shows_the_shorten_box(): void
    {
        $this->get('/')->assertOk()->assertSee('id="lf-shorten"', false);
    }
}

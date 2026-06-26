<?php

namespace Tests\Feature;

use App\Models\BioMessage;
use App\Models\BioPage;
use App\Models\BioSubscriber;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Analytics\BioAnalytics;
use App\Support\BioEmbed;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrBioPixelTest extends TestCase
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
            'alias' => 'a'.Str::random(6),
            'long_url' => 'https://example.com',
            'type' => 'direct',
            'safety_status' => 'safe',
        ], $attrs));
    }

    public function test_qr_renders_svg_for_a_link(): void
    {
        $user = User::factory()->create();
        $link = $this->makeLink($user);

        // The per-link quick QR is a stateless download; the standalone QR
        // studio is what persists designs (see QrStudioTest).
        $response = $this->actingAs($user)->get(route('links.qr.render', ['link' => $link->id, 'format' => 'svg', 'fg' => '#10b981']));

        $response->assertOk();
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_qr_is_owner_only(): void
    {
        $link = $this->makeLink(User::factory()->create());
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('links.qr', $link))->assertForbidden();
        $this->actingAs($intruder)->get(route('links.qr.render', $link))->assertForbidden();
    }

    public function test_bio_page_is_created_with_link_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', [
            'slug' => 'ada',
            'title' => 'Ada Lovelace',
            'is_published' => '1',
            'design' => json_encode(['headerLayout' => 'classic', 'font' => 'jakarta']),
            'settings' => json_encode(['description' => 'First programmer']),
            'social' => json_encode([['platform' => 'github', 'url' => 'https://github.com/ada']]),
            'blocks' => json_encode([
                ['type' => 'link', 'label' => 'Website', 'url' => 'https://example.com'],
                ['type' => 'link', 'label' => 'Blog', 'url' => 'https://blog.example.com'],
                ['type' => 'link', 'label' => 'Empty', 'url' => 'not-a-url'], // skipped
                ['type' => 'heading', 'text' => 'Find me'],
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'ada')->firstOrFail();
        $this->assertTrue($page->is_published);
        $this->assertSame(3, $page->blocks()->count()); // 2 links + 1 heading; invalid URL dropped
        $this->assertSame(1, count($page->social_links));
        $this->assertSame('First programmer', $page->setting('description'));
    }

    public function test_bio_preview_renders_unsaved_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio/preview', [
            'slug' => 'x',
            'title' => 'Preview Name',
            'design' => json_encode(['bg' => ['type' => 'gradient', 'gradStart' => '#10b981', 'gradStop' => '#0f766e', 'gradAngle' => 160], 'textColor' => '#ffffff']),
            'settings' => json_encode(['description' => 'tagline here']),
            'social' => json_encode([['platform' => 'x', 'url' => 'https://x.com/a']]),
            'blocks' => json_encode([['type' => 'link', 'label' => 'Go now', 'url' => 'https://example.com']]),
        ])->assertOk()
            ->assertSee('Preview Name')
            ->assertSee('Go now')
            ->assertSee('linear-gradient', false)
            ->assertSee('/vendor/social/x.svg', false);
    }

    public function test_reserved_bio_handle_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', ['slug' => 'admin'])->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('bio_pages', ['slug' => 'admin']);
    }

    public function test_published_bio_renders_publicly_and_counts_views(): void
    {
        $user = User::factory()->create();
        $page = $user->bioPages()->create([
            'slug' => 'me', 'title' => 'Me',
            'settings' => ['description' => 'hello there'],
            'theme' => ['color' => '#10b981'], 'is_published' => true,
        ]);
        $page->blocks()->create(['type' => 'link', 'content' => ['label' => 'Visit', 'url' => 'https://example.com'], 'sort' => 0, 'is_active' => true]);

        $this->get('/me')->assertOk()->assertSee('Me')->assertSee('Visit');
        $this->assertSame(1, (int) $page->fresh()->views);
    }

    public function test_unpublished_bio_returns_404(): void
    {
        $user = User::factory()->create();
        $user->bioPages()->create(['slug' => 'draft', 'theme' => ['color' => '#10b981'], 'is_published' => false]);

        $this->get('/draft')->assertNotFound();
    }

    public function test_bio_image_upload_returns_a_public_url(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/bio/upload', ['image' => UploadedFile::fake()->image('avatar.png', 40, 40)]);
        $res->assertOk()->assertJsonStructure(['url']);

        $url = $res->json('url');
        $this->assertStringContainsString('/uploads/bio/', $url);

        $path = public_path('uploads/bio/'.basename($url));
        $this->assertFileExists($path);
        @unlink($path); // clean up the artifact
    }

    public function test_bio_supports_extended_block_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', [
            'slug' => 'multi', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'phone', 'phone' => '+15551234567'],
                ['type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
                ['type' => 'divider'],
                ['type' => 'email', 'email' => 'me@example.com'],
                ['type' => 'whatsapp', 'phone' => '15551234567', 'message' => 'hi'],
                ['type' => 'email', 'email' => 'not-an-email'], // dropped
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'multi')->firstOrFail();
        $this->assertSame(5, $page->blocks()->count());
        $this->assertDatabaseHas('bio_blocks', ['bio_page_id' => $page->id, 'type' => 'video']);
        $this->assertDatabaseHas('bio_blocks', ['bio_page_id' => $page->id, 'type' => 'divider']);
    }

    public function test_bio_supports_embed_map_and_countdown_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', [
            'slug' => 'rich', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'embed', 'url' => 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT'],
                ['type' => 'map', 'query' => 'Eiffel Tower, Paris'],
                ['type' => 'countdown', 'label' => 'Launch in', 'date' => '2027-01-01T00:00'],
                ['type' => 'embed', 'url' => 'https://example.com/not-embeddable'], // dropped: no provider
                ['type' => 'map', 'query' => ''], // dropped: empty
                ['type' => 'countdown', 'date' => ''], // dropped: no date
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'rich')->firstOrFail();
        $this->assertSame(3, $page->blocks()->count());
        $this->assertDatabaseHas('bio_blocks', ['bio_page_id' => $page->id, 'type' => 'embed']);
        $this->assertDatabaseHas('bio_blocks', ['bio_page_id' => $page->id, 'type' => 'map']);
        $this->assertDatabaseHas('bio_blocks', ['bio_page_id' => $page->id, 'type' => 'countdown']);

        // Public render uses the real provider embed URLs + a live countdown element.
        $this->get('/rich')->assertOk()
            ->assertSee('open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT', false)
            ->assertSee('maps.google.com/maps?q=Eiffel+Tower', false)
            ->assertSee('data-countdown="2027-01-01T00:00"', false)
            ->assertSee('Launch in');
    }

    public function test_bio_supports_faq_and_product_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', [
            'slug' => 'shop', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'faq', 'label' => 'FAQ', 'text' => "Do you ship worldwide? | Yes.\nReturns? | 30 days."],
                ['type' => 'product', 'label' => 'Headphones', 'price' => '$199', 'image' => 'https://ex.com/h.jpg', 'url' => 'https://ex.com/buy', 'text' => 'Great sound.'],
                ['type' => 'faq', 'text' => ''], // dropped: no body
                ['type' => 'product', 'url' => 'not-a-url'], // dropped: no label and no valid url
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'shop')->firstOrFail();
        $this->assertSame(2, $page->blocks()->count());

        $this->get('/shop')->assertOk()
            ->assertSee('Do you ship worldwide?')
            ->assertSee('30 days.')
            ->assertSee('<details', false)
            ->assertSee('Headphones')
            ->assertSee('$199')
            ->assertSee('Buy now')
            ->assertSee('https://ex.com/buy', false);
    }

    public function test_bio_newsletter_signup_is_captured_and_deduped(): void
    {
        $page = User::factory()->create()->bioPages()->create(['slug' => 'nl', 'is_published' => true]);

        $this->post(route('bio.subscribe', 'nl'), ['email' => 'fan@example.com', 'name' => 'Fan'])
            ->assertRedirect('/nl')->assertSessionHas('bio_form_ok', 'subscribe');
        $this->assertDatabaseHas('bio_subscribers', ['bio_page_id' => $page->id, 'email' => 'fan@example.com']);

        // Same email (any case) does not create a second row.
        $this->post(route('bio.subscribe', 'nl'), ['email' => 'FAN@example.com']);
        $this->assertSame(1, BioSubscriber::where('bio_page_id', $page->id)->count());

        // Honeypot field set => silently dropped.
        $this->post(route('bio.subscribe', 'nl'), ['email' => 'bot@example.com', 'website' => 'spam']);
        $this->assertDatabaseMissing('bio_subscribers', ['email' => 'bot@example.com']);
    }

    public function test_bio_contact_message_is_captured(): void
    {
        $page = User::factory()->create()->bioPages()->create(['slug' => 'ct', 'is_published' => true]);

        $this->post(route('bio.contact', 'ct'), ['name' => 'Sam', 'email' => 's@example.com', 'message' => 'Hello there'])
            ->assertRedirect('/ct')->assertSessionHas('bio_form_ok', 'contact');
        $this->assertDatabaseHas('bio_messages', ['bio_page_id' => $page->id, 'message' => 'Hello there']);

        $this->post(route('bio.contact', 'ct'), ['name' => 'NoMsg'])->assertSessionHasErrors('message');
    }

    public function test_bio_leads_dashboard_and_export_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $page = $owner->bioPages()->create(['slug' => 'ld', 'is_published' => true]);
        BioSubscriber::create(['bio_page_id' => $page->id, 'email' => 'a@example.com', 'name' => 'A']);
        BioMessage::create(['bio_page_id' => $page->id, 'message' => 'a question']);

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('bio.leads', $page))->assertForbidden();
        $this->actingAs($intruder)->get(route('bio.leads.export', $page))->assertForbidden();

        $this->actingAs($owner)->get(route('bio.leads', $page))->assertOk()
            ->assertSee('a@example.com')->assertSee('a question');

        $res = $this->actingAs($owner)->get(route('bio.leads.export', [$page, 'type' => 'subscribers']));
        $res->assertOk();
        $this->assertStringContainsString('a@example.com', $res->streamedContent());
    }

    public function test_bio_supports_newsletter_contact_and_rss_blocks(): void
    {
        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0"?><rss version="2.0"><channel><item><title>First post</title><link>https://blog.example.com/1</link></item><item><title>Second</title><link>https://blog.example.com/2</link></item></channel></rss>',
                200,
            ),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/bio', [
            'slug' => 'widgets', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'newsletter', 'label' => 'Join', 'text' => 'Weekly tips', 'button' => 'Subscribe'],
                ['type' => 'contact', 'label' => 'Reach out'],
                ['type' => 'rss', 'label' => 'Blog', 'url' => 'https://blog.example.com/feed', 'count' => '5'],
                ['type' => 'rss', 'url' => 'not-a-url'], // dropped: invalid feed URL
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'widgets')->firstOrFail();
        $this->assertSame(3, $page->blocks()->count());

        $this->get('/widgets')->assertOk()
            ->assertSee(route('bio.subscribe', 'widgets'), false)
            ->assertSee(route('bio.contact', 'widgets'), false)
            ->assertSee('First post')
            ->assertSee('https://blog.example.com/1', false);
    }

    public function test_bio_builder_offers_every_block_type(): void
    {
        $res = $this->actingAs(User::factory()->create())->get(route('bio.create'));
        $res->assertOk();
        foreach (['embed', 'map', 'countdown', 'faq', 'product', 'newsletter', 'contact', 'rss', 'tagline', 'html', 'vcard', 'carousel', 'chat', 'paypal', 'audio', 'pdf', 'videofile'] as $type) {
            $res->assertSee('data-add-block="'.$type.'"', false);
        }
    }

    public function test_bio_html_block_is_sanitized(): void
    {
        // Direct sanitizer contract.
        $clean = HtmlSanitizer::clean('<p>Hi <b>x</b><script>alert(1)</script> <a href="javascript:alert(2)">a</a> <a href="https://ok.com">b</a></p><iframe src="https://evil"></iframe>');
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('<b>x</b>', $clean);
        $this->assertStringContainsString('href="https://ok.com"', $clean);

        // Through the store flow + public render.
        $user = User::factory()->create();
        $this->actingAs($user)->post('/bio', [
            'slug' => 'htmlp', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([['type' => 'html', 'html' => '<p>Safe <strong>bold</strong></p><script>evil()</script>']]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'htmlp')->firstOrFail();
        $this->assertStringNotContainsString('<script', $page->blocks()->first()->content['html']);
        $this->get('/htmlp')->assertOk()->assertSee('bold')->assertDontSee('evil()', false);
    }

    public function test_bio_vcard_block_downloads_contact_card(): void
    {
        $page = User::factory()->create()->bioPages()->create(['slug' => 'card', 'is_published' => true]);
        $block = $page->blocks()->create([
            'type' => 'vcard', 'sort' => 0, 'is_active' => true,
            'content' => ['label' => 'Ada Lovelace', 'org' => 'Engines', 'phone' => '+15551234567', 'email' => 'ada@example.com'],
        ]);

        $res = $this->get(route('bio.vcard', ['slug' => 'card', 'block' => $block->id]));
        $res->assertOk();
        $this->assertStringContainsString('text/vcard', $res->headers->get('content-type'));
        $body = $res->getContent();
        $this->assertStringContainsString('BEGIN:VCARD', $body);
        $this->assertStringContainsString('FN:Ada Lovelace', $body);
        $this->assertStringContainsString('TEL:+15551234567', $body);

        // Non-vcard block id is rejected.
        $other = $page->blocks()->create(['type' => 'link', 'content' => ['label' => 'x', 'url' => 'https://e.com'], 'sort' => 1, 'is_active' => true]);
        $this->get(route('bio.vcard', ['slug' => 'card', 'block' => $other->id]))->assertNotFound();
    }

    public function test_bio_media_file_upload_accepts_audio_and_pdf(): void
    {
        $user = User::factory()->create();

        foreach ([['song.mp3', 'audio/mpeg'], ['cv.pdf', 'application/pdf']] as [$name, $mime]) {
            $res = $this->actingAs($user)->post('/bio/upload-file', [
                'file' => UploadedFile::fake()->create($name, 200, $mime),
            ]);
            $res->assertOk()->assertJsonStructure(['url']);
            $path = public_path('uploads/bio/'.basename($res->json('url')));
            $this->assertFileExists($path);
            @unlink($path);
        }

        // An executable/script upload is rejected.
        $this->actingAs($user)->post('/bio/upload-file', ['file' => UploadedFile::fake()->create('x.php', 10, 'text/x-php')])
            ->assertSessionHasErrors('file');
    }

    public function test_bio_supports_communication_blocks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/bio', [
            'slug' => 'comm', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'tagline', 'text' => 'My tagline here'],
                ['type' => 'carousel', 'text' => "https://ex.com/1.jpg\nhttps://ex.com/2.jpg\nnot-a-url"],
                ['type' => 'paypal', 'username' => 'ada$bad chars', 'price' => 'USD 15', 'label' => 'Tip me'],
                ['type' => 'audio', 'label' => 'Track', 'url' => 'https://ex.com/a.mp3'],
                ['type' => 'videofile', 'url' => 'https://ex.com/v.mp4'],
                ['type' => 'chat', 'provider' => 'tawkto', 'id' => 'abc/def'],
                ['type' => 'chat', 'provider' => 'evilprovider', 'id' => 'x'], // dropped: not allowlisted
                ['type' => 'paypal', 'username' => ''], // dropped: no username
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'comm')->firstOrFail();
        $this->assertSame(6, $page->blocks()->count());

        // PayPal username sanitized, amount digits-only.
        $pp = $page->blocks()->where('type', 'paypal')->first()->content;
        $this->assertSame('adabadchars', $pp['username']);
        $this->assertSame('15', $pp['amount']);
        // Carousel kept only the 2 valid URLs.
        $this->assertCount(2, $page->blocks()->where('type', 'carousel')->first()->content['images']);

        $this->get('/comm')->assertOk()
            ->assertSee('My tagline here')
            ->assertSee('paypalme/adabadchars/15', false)
            ->assertSee('embed.tawk.to/abc/def', false)
            ->assertSee('<audio', false)
            ->assertSee('<video', false);
    }

    public function test_bio_builder_renders_categorised_block_palette(): void
    {
        $res = $this->actingAs(User::factory()->create())->get(route('bio.create'));

        $res->assertOk()
            ->assertSee('Add a block')
            ->assertSee('Basics')->assertSee('Media')->assertSee('Widgets')           // categories
            ->assertSee('data-add-block="testimonial"', false)                        // new blocks present
            ->assertSee('data-add-block="apps"', false)
            ->assertSee('data-add-block="gallery"', false)
            ->assertSee('vendor/social/paypal.svg', false)                            // real brand logo icon
            ->assertSee('id="bio-social-options-data"', false)                        // searchable social picker data island
            ->assertSee('"key":"instagram"', false);                                  // platforms available to the picker
    }

    public function test_bio_supports_spacer_gallery_apps_and_testimonial_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/bio', [
            'slug' => 'rich2', 'is_published' => '1',
            'design' => json_encode([]), 'settings' => json_encode([]), 'social' => json_encode([]),
            'blocks' => json_encode([
                ['type' => 'spacer', 'size' => 'lg'],
                ['type' => 'gallery', 'text' => "https://ex.com/1.jpg\nhttps://ex.com/2.jpg\nnope"],
                ['type' => 'apps', 'ios' => 'https://apps.apple.com/app/id1', 'android' => 'https://play.google.com/store/apps/details?id=x'],
                ['type' => 'testimonial', 'text' => 'Best tool ever', 'label' => 'Jane Doe', 'image' => 'https://ex.com/j.jpg'],
                ['type' => 'apps', 'ios' => 'not-a-url'],      // dropped: no valid store URL
                ['type' => 'testimonial', 'text' => ''],        // dropped: no quote
            ]),
        ])->assertRedirect();

        $page = BioPage::where('slug', 'rich2')->firstOrFail();
        $this->assertSame(4, $page->blocks()->count());
        $this->assertCount(2, $page->blocks()->where('type', 'gallery')->first()->content['images']); // invalid URL dropped
        $this->assertSame('lg', $page->blocks()->where('type', 'spacer')->first()->content['size']);

        $this->get('/rich2')->assertOk()
            ->assertSee('Best tool ever')
            ->assertSee('Jane Doe')
            ->assertSee('App Store')
            ->assertSee('Google Play');
    }

    public function test_bio_embed_resolves_known_providers_only(): void
    {
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', BioEmbed::resolve('https://youtu.be/dQw4w9WgXcQ')['src']);
        $this->assertSame('https://player.vimeo.com/video/76979871', BioEmbed::resolve('https://vimeo.com/76979871')['src']);
        $this->assertSame('https://open.spotify.com/embed/track/abc123', BioEmbed::resolve('https://open.spotify.com/track/abc123')['src']);
        $this->assertStringContainsString('form.typeform.com/to/AbC123', BioEmbed::resolve('https://mysite.typeform.com/to/AbC123')['src']);
        $this->assertNull(BioEmbed::resolve('https://example.com/whatever'));
        $this->assertNull(BioEmbed::resolve(''));
    }

    public function test_password_protected_bio_gates_then_unlocks(): void
    {
        $page = User::factory()->create()->bioPages()->create([
            'slug' => 'locked', 'title' => 'Locked', 'is_published' => true,
            'settings' => ['password' => bcrypt('secret123')],
        ]);
        $page->blocks()->create(['type' => 'link', 'content' => ['label' => 'Secret link', 'url' => 'https://example.com'], 'sort' => 0, 'is_active' => true]);

        $this->get('/locked')->assertOk()->assertSee('This page is protected')->assertDontSee('Secret link');
        $this->post(route('bio.unlock', 'locked'), ['password' => 'wrong'])->assertRedirect('/locked');
        $this->get('/locked')->assertSee('This page is protected');
        $this->post(route('bio.unlock', 'locked'), ['password' => 'secret123'])->assertRedirect('/locked');
        $this->get('/locked')->assertSee('Secret link')->assertDontSee('This page is protected');
    }

    public function test_sensitive_content_warning_gates_then_reveals(): void
    {
        $page = User::factory()->create()->bioPages()->create([
            'slug' => 'nsfw', 'is_published' => true, 'settings' => ['sensitive' => true],
        ]);
        $page->blocks()->create(['type' => 'link', 'content' => ['label' => 'Enter here', 'url' => 'https://example.com'], 'sort' => 0, 'is_active' => true]);

        $this->get('/nsfw')->assertSee('Sensitive content')->assertDontSee('Enter here');
        $this->post(route('bio.reveal', 'nsfw'))->assertRedirect('/nsfw');
        $this->get('/nsfw')->assertSee('Enter here');
    }

    public function test_bio_click_tracking_redirects_and_analytics_aggregates(): void
    {
        $page = User::factory()->create()->bioPages()->create(['slug' => 'trk', 'is_published' => true]);
        $block = $page->blocks()->create(['type' => 'link', 'content' => ['label' => 'Go', 'url' => 'https://example.com'], 'sort' => 0, 'is_active' => true]);

        // Record + aggregate deterministically (HTTP after-response timing is flaky to
        // assert on under the shared test app, so exercise the recorder directly).
        $bio = app(BioAnalytics::class);
        $req = Request::create('/trk', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120', 'REMOTE_ADDR' => '8.8.8.8']);
        $bio->record($page->id, null, 'view', $req);
        $bio->record($page->id, $block->id, 'click', $req);

        $this->assertDatabaseHas('bio_events', ['bio_page_id' => $page->id, 'type' => 'view']);
        $totals = $bio->totals(fn ($q) => $q->where('bio_page_id', $page->id), now()->subDay(), now());
        $this->assertSame(1, $totals['views']);
        $this->assertSame(1, $totals['clicks']);

        // The tracked link redirects to the destination.
        $this->get(route('bio.track', ['slug' => 'trk', 'block' => $block->id]))->assertRedirect('https://example.com');
    }

    public function test_pixel_attaches_and_fires_on_splash_link(): void
    {
        $user = User::factory()->create(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        $this->actingAs($user)->post('/pixels', ['provider' => 'facebook', 'pixel_id' => '1234567890', 'name' => 'Main'])
            ->assertRedirect();
        $pixel = $user->pixels()->firstOrFail();

        $this->actingAs($user)->post('/links', [
            'long_url' => 'https://example.com/dest', 'alias' => 'sp', 'type' => 'splash', 'pixels' => [$pixel->id],
        ])->assertRedirect(route('links.index'));

        $link = $user->links()->where('alias', 'sp')->firstOrFail();
        $this->assertTrue($link->pixels()->where('pixels.id', $pixel->id)->exists());

        $this->get('/sp')->assertOk()
            ->assertSee('fbq', false)
            ->assertSee('1234567890', false);
    }

    public function test_pixel_destroy_is_owner_only(): void
    {
        $pixel = User::factory()->create()->pixels()->create(['provider' => 'google', 'pixel_id' => 'G-123']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete(route('pixels.destroy', $pixel))->assertForbidden();
    }

    public function test_pixel_on_a_direct_link_fires_via_the_splash(): void
    {
        $user = User::factory()->create();
        $pixel = $user->pixels()->create(['provider' => 'facebook', 'pixel_id' => '99887766']);
        $link = $this->makeLink($user, ['type' => 'direct', 'alias' => 'directpx']);
        $link->pixels()->attach($pixel->id);

        // A direct link WITH a pixel must render the splash so the pixel can fire,
        // instead of an instant 302 that would skip it.
        $this->get('/directpx')->assertOk()
            ->assertSee('fbq', false)
            ->assertSee('99887766', false);
    }

    public function test_direct_link_without_pixels_redirects_instantly(): void
    {
        $user = User::factory()->create();
        $link = $this->makeLink($user, ['type' => 'direct', 'alias' => 'plain', 'long_url' => 'https://example.com/go']);

        $this->get('/plain')->assertRedirect('https://example.com/go');
    }

    public function test_every_provider_renders_its_tracking_script(): void
    {
        $user = User::factory()->create();

        // provider => a unique signature from its official snippet's loader URL.
        $providers = [
            'facebook' => 'connect.facebook.net',
            'google' => 'googletagmanager.com/gtag/js',
            'tiktok' => 'analytics.tiktok.com',
            'linkedin' => 'snap.licdn.com',
            'twitter' => 'static.ads-twitter.com',
            'pinterest' => 's.pinimg.com/ct/core.js',
            'quora' => 'a.quora.com/qevents.js',
            'bing' => 'bat.bing.com/bat.js',
            'snapchat' => 'sc-static.net/scevent.min.js',
            'reddit' => 'redditstatic.com/ads/pixel.js',
            'gtm' => 'googletagmanager.com/gtm.js',
        ];

        foreach ($providers as $provider => $needle) {
            $pixelId = 'PX-'.strtoupper($provider);
            $pixel = $user->pixels()->create(['provider' => $provider, 'pixel_id' => $pixelId]);
            $link = $this->makeLink($user, ['type' => 'direct', 'alias' => 'px'.$provider]);
            $link->pixels()->attach($pixel->id);

            $content = $this->get('/px'.$provider)->assertOk()->getContent();
            $this->assertStringContainsString($needle, $content, "{$provider}: loader script missing");
            $this->assertStringContainsString($pixelId, $content, "{$provider}: pixel id missing");
        }
    }
}

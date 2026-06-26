<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\User;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(); // default domain + plans
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('uploads/bio/*')) ?: [] as $f) {
            if (! str_ends_with($f, '.htaccess')) {
                @unlink($f);
            }
        }
        parent::tearDown();
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
    }

    public function test_safe_url_blocks_internal_targets(): void
    {
        foreach (['http://127.0.0.1/x', 'http://localhost/x', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/', 'ftp://example.com/x', 'http://[::1]/'] as $bad) {
            $this->assertFalse(SafeUrl::isSafe($bad), "should block {$bad}");
        }
        // Unresolvable public-looking host is allowed (not an SSRF vector).
        $this->assertTrue(SafeUrl::isSafe('https://hook.example/endpoint'));
    }

    public function test_webhook_creation_rejects_internal_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('webhooks.store'), [
            'url' => 'http://169.254.169.254/latest/meta-data/iam',
            'events' => ['link.created'],
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, $user->webhooks()->count());
    }

    public function test_webhook_send_skips_internal_target(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $wh = $user->webhooks()->create([
            'url' => 'http://127.0.0.1/x', 'events' => ['link.created'], 'secret' => 's', 'is_active' => true,
        ]);

        (new SendWebhook($wh->id, 'link.created', ['id' => 1]))->handle();

        Http::assertNothingSent();
    }

    public function test_bio_upload_uses_a_content_derived_extension_not_the_client_name(): void
    {
        $user = User::factory()->create();

        // A real PNG on disk, uploaded under a ".pht" filename — a PHP-handler extension
        // Laravel's mimes rule does NOT block, but which Apache often executes. Our fix
        // derives the stored extension from content, so it can never land as ".pht".
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';
        $img = imagecreatetruecolor(16, 16);
        imagepng($img, $path);
        imagedestroy($img);
        $file = new UploadedFile($path, 'evil.pht', 'image/png', null, true);

        $url = $this->actingAs($user)->post(route('bio.upload'), ['image' => $file])
            ->assertOk()->json('url');

        $this->assertDoesNotMatchRegularExpression('/\.(php|pht|phtml|phar)$/i', $url, 'must not store an executable extension');
        $this->assertMatchesRegularExpression('/\.(png|jpg|gif|webp)$/i', $url);
        $this->assertFileExists(public_path('uploads/bio/'.basename($url)));
    }

    public function test_bio_theme_color_cannot_inject_script(): void
    {
        $user = User::factory()->create();
        $page = $user->bioPages()->create([
            'slug' => 'xsstest',
            'title' => 'XSS',
            'is_published' => true,
            'theme' => ['bg' => ['type' => 'color', 'color' => '#fff;}</style><script>alert(document.cookie)</script><style>{']],
            'settings' => [],
            'social_links' => [],
        ]);

        $html = $this->get('/'.$page->slug)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(document.cookie)</script>', $html);
        $this->assertStringNotContainsString('</style><script>', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use App\Services\Linking\AliasGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DocsRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_docs_serves_documentation_and_is_not_resolved_as_a_short_link(): void
    {
        // Reproduce the reported bug: a link claims the "docs" alias (an old demo
        // seed did exactly this, pointing at example.com/docs). It must NOT win.
        $user = User::factory()->create();
        $user->links()->create([
            'domain_id' => Domain::where('is_default', true)->value('id'),
            'alias' => 'docs',
            'long_url' => 'https://example.com/docs',
        ]);

        // A bare /docs points at the index FILE (root-relative) - it is NOT
        // redirected to the link's target, and the host is not baked in.
        $this->get('/docs')->assertStatus(302)->assertHeader('Location', '/docs/index.html');

        // The index file itself is served, not a short-link redirect.
        $res = $this->get('/docs/index.html');
        $res->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $res->baseResponse);
        $this->assertSame(
            realpath(public_path('docs/index.html')),
            $res->baseResponse->getFile()->getRealPath(),
        );
    }

    public function test_docs_assets_are_served_through_the_route(): void
    {
        $this->get('/docs/assets/style.css')->assertOk();
        $this->get('/docs/assets/script.js')->assertOk();
    }

    public function test_missing_docs_file_is_not_found(): void
    {
        $this->get('/docs/does-not-exist.html')->assertNotFound();
    }

    public function test_docs_is_a_reserved_alias(): void
    {
        $error = app(AliasGenerator::class)->validateCustom('docs', 1);

        $this->assertNotNull($error);
        $this->assertStringContainsString('reserved', $error);
    }
}

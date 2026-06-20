<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_blog_index_shows_published_not_drafts(): void
    {
        Post::create(['title' => 'Live Post', 'slug' => 'live-post', 'status' => 'published', 'published_at' => now(), 'body' => 'Hello']);
        Post::create(['title' => 'Hidden Draft', 'slug' => 'hidden-draft', 'status' => 'draft', 'body' => 'x']);

        $this->get('/blog')->assertOk()->assertSee('Live Post')->assertDontSee('Hidden Draft');
    }

    public function test_blog_post_renders_markdown_and_drafts_404(): void
    {
        Post::create(['title' => 'Readable', 'slug' => 'readable', 'status' => 'published', 'published_at' => now(), 'body' => "## A Heading\n\nSome text."]);
        $this->get('/blog/readable')->assertOk()->assertSee('Readable')->assertSee('A Heading', false);

        Post::create(['title' => 'Nope', 'slug' => 'nope', 'status' => 'draft', 'body' => 'x']);
        $this->get('/blog/nope')->assertNotFound();
    }

    public function test_help_index_groups_published_articles(): void
    {
        HelpArticle::create(['title' => 'Getting started', 'slug' => 'getting-started', 'category' => 'Basics', 'status' => 'published', 'body' => 'x']);
        HelpArticle::create(['title' => 'Secret', 'slug' => 'secret', 'category' => 'Basics', 'status' => 'draft', 'body' => 'x']);

        $this->get('/help')->assertOk()->assertSee('Getting started')->assertSee('Basics')->assertDontSee('Secret');
    }

    public function test_help_article_renders_and_counts_views(): void
    {
        $a = HelpArticle::create(['title' => 'How to shorten', 'slug' => 'how-to-shorten', 'category' => 'Basics', 'status' => 'published', 'body' => 'Body here']);

        $this->get('/help/how-to-shorten')->assertOk()->assertSee('How to shorten');
        $this->assertSame(1, $a->fresh()->views);
    }

    public function test_admin_can_create_a_published_post_with_autoslug(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/blog', ['title' => 'My First Post', 'status' => 'published', 'body' => 'hi'])
            ->assertRedirect(route('admin.blog.index'));

        $post = Post::where('title', 'My First Post')->firstOrFail();
        $this->assertSame('my-first-post', $post->slug);
        $this->assertNotNull($post->published_at);
    }

    public function test_admin_can_upload_a_cover_image(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg', 800, 450);

        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'With Cover', 'status' => 'draft', 'body' => 'x', 'cover_file' => $file,
        ])->assertRedirect(route('admin.blog.index'));

        $cover = \App\Models\Post::where('title', 'With Cover')->value('cover_image');
        $this->assertNotEmpty($cover);
        $this->assertStringContainsString('uploads/blog/', $cover);
        $this->assertFileExists(public_path(parse_url($cover, PHP_URL_PATH)));
    }

    public function test_cover_url_is_used_when_no_file_uploaded(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', [
            'title' => 'URL Cover', 'status' => 'draft', 'body' => 'x', 'cover_image' => 'https://cdn.example.com/a.png',
        ]);

        $this->assertSame('https://cdn.example.com/a.png', \App\Models\Post::where('title', 'URL Cover')->value('cover_image'));
    }

    public function test_draft_post_has_no_published_at(): void
    {
        $this->actingAs($this->admin())->post('/admin/blog', ['title' => 'A Draft', 'status' => 'draft', 'body' => 'hi']);

        $this->assertNull(Post::where('title', 'A Draft')->value('published_at'));
    }

    public function test_admin_can_create_help_article(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/help', ['title' => 'FAQ Item', 'category' => 'FAQ', 'status' => 'published', 'body' => 'a'])
            ->assertRedirect(route('admin.help.index'));

        $this->assertDatabaseHas('help_articles', ['slug' => 'faq-item', 'category' => 'FAQ']);
    }

    public function test_slug_collisions_are_resolved(): void
    {
        Post::create(['title' => 'Dup', 'slug' => 'dup', 'status' => 'published', 'published_at' => now(), 'body' => 'x']);

        $this->actingAs($this->admin())->post('/admin/blog', ['title' => 'Dup', 'status' => 'published', 'body' => 'y']);

        $this->assertDatabaseHas('posts', ['slug' => 'dup-2']);
    }

    public function test_non_admin_cannot_manage_blog(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/blog')->assertForbidden();
    }
}

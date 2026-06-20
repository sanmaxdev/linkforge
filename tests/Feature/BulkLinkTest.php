<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function user(string $plan = 'pro'): User
    {
        return User::factory()->create(['plan_id' => Plan::where('slug', $plan)->value('id')]);
    }

    public function test_bulk_paste_creates_links(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/links/bulk', [
            'urls' => "https://example.com/a\nhttps://example.com/b\nhttps://example.com/c",
        ])->assertRedirect();

        $this->assertSame(3, $user->links()->count());
    }

    public function test_bulk_skips_invalid_and_duplicate_urls(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/links/bulk', [
            'urls' => "https://example.com/a\nnot-a-url\nhttps://example.com/a\nhttps://example.com/b",
        ]);

        $this->assertSame(2, $user->links()->count()); // a + b; duplicate a and the invalid line are skipped
    }

    public function test_bulk_respects_plan_link_limit(): void
    {
        $user = $this->user('free'); // max_links = 25

        $urls = collect(range(1, 30))->map(fn ($i) => "https://example.com/p{$i}")->implode("\n");
        $this->actingAs($user)->post('/links/bulk', ['urls' => $urls]);

        $this->assertSame(25, $user->links()->count());
    }

    public function test_bulk_applies_campaign_and_tags(): void
    {
        $user = $this->user();
        $campaign = $user->campaigns()->create(['name' => 'Import']);

        $this->actingAs($user)->post('/links/bulk', [
            'urls' => "https://example.com/a\nhttps://example.com/b",
            'campaign_id' => $campaign->id,
            'tags' => 'imported, q2',
        ]);

        $link = $user->links()->first();
        $this->assertSame($campaign->id, $link->campaign_id);
        $this->assertEqualsCanonicalizing(['imported', 'q2'], $link->tags);
    }

    public function test_csv_import_with_standard_header(): void
    {
        $user = $this->user();
        $csv = "long url,alias,title,tags\nhttps://example.com/one,promoone,First,sale\nhttps://example.com/two,,Second,news\n";
        $file = UploadedFile::fake()->createWithContent('links.csv', $csv);

        $this->actingAs($user)->post('/links/import', ['file' => $file])->assertRedirect();

        $this->assertSame(2, $user->links()->count());
        $this->assertDatabaseHas('links', ['alias' => 'promoone', 'long_url' => 'https://example.com/one']);
    }

    public function test_csv_import_bitly_export_format(): void
    {
        $user = $this->user();
        $csv = "Bitlink,Long URL,Title,Tags\nhttps://bit.ly/3xYz12,https://example.com/page,My Page,marketing\n";
        $file = UploadedFile::fake()->createWithContent('bitly.csv', $csv);

        $this->actingAs($user)->post('/links/import', ['file' => $file]);

        $this->assertDatabaseHas('links', ['alias' => '3xYz12', 'long_url' => 'https://example.com/page']);
    }

    public function test_csv_import_headerless_positional(): void
    {
        $user = $this->user();
        $csv = "https://example.com/x,xcode\nhttps://example.com/y\n";
        $file = UploadedFile::fake()->createWithContent('plain.csv', $csv);

        $this->actingAs($user)->post('/links/import', ['file' => $file]);

        $this->assertSame(2, $user->links()->count());
        $this->assertDatabaseHas('links', ['alias' => 'xcode']);
    }

    public function test_bulk_page_loads(): void
    {
        $this->actingAs($this->user())->get('/links/bulk')->assertOk()->assertSee('Bulk shorten');
    }
}

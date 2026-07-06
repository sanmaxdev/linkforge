<?php

namespace Tests\Unit;

use App\Models\Link;
use Carbon\Carbon;
use Tests\TestCase;

class LinkTest extends TestCase
{
    // -------------------------------------------------------------------------
    // normalizeTags
    // -------------------------------------------------------------------------

    public function test_normalize_tags_returns_null_for_empty_string(): void
    {
        $this->assertNull(Link::normalizeTags(''));
    }

    public function test_normalize_tags_returns_null_for_empty_array(): void
    {
        $this->assertNull(Link::normalizeTags([]));
    }

    public function test_normalize_tags_returns_null_for_null(): void
    {
        $this->assertNull(Link::normalizeTags(null));
    }

    public function test_normalize_tags_splits_comma_separated_string(): void
    {
        $result = Link::normalizeTags('marketing,sales,growth');

        $this->assertSame(['marketing', 'sales', 'growth'], $result);
    }

    public function test_normalize_tags_splits_by_multiple_delimiters(): void
    {
        $result = Link::normalizeTags("foo,bar;baz|qux\ncorge");

        $this->assertSame(['foo', 'bar', 'baz', 'qux', 'corge'], $result);
    }

    public function test_normalize_tags_lowercases_tags(): void
    {
        $result = Link::normalizeTags('Marketing,SALES');

        $this->assertSame(['marketing', 'sales'], $result);
    }

    public function test_normalize_tags_strips_special_characters(): void
    {
        $result = Link::normalizeTags('hello@world!,foo#bar');

        $this->assertSame(['helloworld', 'foobar'], $result);
    }

    public function test_normalize_tags_deduplicates(): void
    {
        $result = Link::normalizeTags('php,laravel,php');

        $this->assertSame(['php', 'laravel'], $result);
    }

    public function test_normalize_tags_caps_at_ten(): void
    {
        $input = 'a,b,c,d,e,f,g,h,i,j,k,l';

        $result = Link::normalizeTags($input);

        $this->assertCount(10, $result);
    }

    public function test_normalize_tags_truncates_each_tag_to_thirty_chars(): void
    {
        $long = str_repeat('a', 40);

        $result = Link::normalizeTags($long);

        $this->assertSame([str_repeat('a', 30)], $result);
    }

    public function test_normalize_tags_accepts_array_input(): void
    {
        $result = Link::normalizeTags(['Laravel', 'PHP']);

        $this->assertSame(['laravel', 'php'], $result);
    }

    public function test_normalize_tags_filters_blank_entries(): void
    {
        $result = Link::normalizeTags('foo,,bar, ,baz');

        $this->assertSame(['foo', 'bar', 'baz'], $result);
    }

    // -------------------------------------------------------------------------
    // appendParams
    // -------------------------------------------------------------------------

    public function test_append_params_returns_url_unchanged_when_params_is_empty(): void
    {
        $link = new Link(['params' => []]);

        $this->assertSame('https://example.com', $link->appendParams('https://example.com'));
    }

    public function test_append_params_returns_url_unchanged_when_params_is_null(): void
    {
        $link = new Link;

        $this->assertSame('https://example.com', $link->appendParams('https://example.com'));
    }

    public function test_append_params_appends_params_to_clean_url(): void
    {
        $link = new Link(['params' => ['utm_source' => 'newsletter', 'utm_medium' => 'email']]);

        $result = $link->appendParams('https://example.com/page');

        $this->assertStringContainsString('utm_source=newsletter', $result);
        $this->assertStringContainsString('utm_medium=email', $result);
    }

    public function test_append_params_existing_url_params_take_precedence(): void
    {
        $link = new Link(['params' => ['utm_source' => 'newsletter']]);

        $result = $link->appendParams('https://example.com?utm_source=direct');

        $this->assertStringContainsString('utm_source=direct', $result);
        $this->assertStringNotContainsString('utm_source=newsletter', $result);
    }

    public function test_append_params_preserves_hash_fragment(): void
    {
        $link = new Link(['params' => ['ref' => 'home']]);

        $result = $link->appendParams('https://example.com#section');

        $this->assertStringContainsString('ref=home', $result);
        $this->assertStringEndsWith('#section', $result);
    }

    public function test_append_params_merges_with_existing_query_and_keeps_hash(): void
    {
        $link = new Link(['params' => ['utm_source' => 'email', 'utm_medium' => 'campaign']]);

        $result = $link->appendParams('https://example.com?ref=social#top');

        $this->assertStringContainsString('ref=social', $result);
        $this->assertStringContainsString('utm_source=email', $result);
        $this->assertStringEndsWith('#top', $result);
    }

    // -------------------------------------------------------------------------
    // isExpired
    // -------------------------------------------------------------------------

    public function test_is_expired_returns_false_when_no_expiry_set(): void
    {
        $link = new Link;

        $this->assertFalse($link->isExpired());
    }

    public function test_is_expired_returns_false_when_expiry_is_in_the_future(): void
    {
        $link = new Link(['expires_at' => Carbon::now()->addDay()]);

        $this->assertFalse($link->isExpired());
    }

    public function test_is_expired_returns_true_when_expiry_is_in_the_past(): void
    {
        $link = new Link(['expires_at' => Carbon::now()->subDay()]);

        $this->assertTrue($link->isExpired());
    }

    public function test_is_expired_returns_true_when_expiry_is_exactly_now(): void
    {
        Carbon::setTestNow(Carbon::now());
        $link = new Link(['expires_at' => Carbon::now()->subSecond()]);

        $this->assertTrue($link->isExpired());

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------------------
    // isOverLimit
    // -------------------------------------------------------------------------

    public function test_is_over_limit_returns_false_when_no_limit_set(): void
    {
        $link = new Link(['clicks' => 9999]);

        $this->assertFalse($link->isOverLimit());
    }

    public function test_is_over_limit_returns_false_when_clicks_are_below_limit(): void
    {
        $link = new Link(['click_limit' => 100, 'clicks' => 50]);

        $this->assertFalse($link->isOverLimit());
    }

    public function test_is_over_limit_returns_true_when_clicks_equal_limit(): void
    {
        $link = new Link(['click_limit' => 100, 'clicks' => 100]);

        $this->assertTrue($link->isOverLimit());
    }

    public function test_is_over_limit_returns_true_when_clicks_exceed_limit(): void
    {
        $link = new Link(['click_limit' => 100, 'clicks' => 101]);

        $this->assertTrue($link->isOverLimit());
    }

    public function test_is_over_limit_handles_zero_limit(): void
    {
        $link = new Link(['click_limit' => 0, 'clicks' => 0]);

        $this->assertTrue($link->isOverLimit());
    }

    // -------------------------------------------------------------------------
    // hasDeepLinks
    // -------------------------------------------------------------------------

    public function test_has_deep_links_returns_false_when_meta_is_empty(): void
    {
        $link = new Link(['meta' => []]);

        $this->assertFalse($link->hasDeepLinks());
    }

    public function test_has_deep_links_returns_true_when_ios_is_set(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => 'myapp://home']]]);

        $this->assertTrue($link->hasDeepLinks());
    }

    public function test_has_deep_links_returns_true_when_android_is_set(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['android' => 'myapp://home']]]);

        $this->assertTrue($link->hasDeepLinks());
    }

    public function test_has_deep_links_returns_true_when_both_are_set(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => 'myapp://home', 'android' => 'myapp://home']]]);

        $this->assertTrue($link->hasDeepLinks());
    }

    // -------------------------------------------------------------------------
    // deepLinkFor
    // -------------------------------------------------------------------------

    public function test_deep_link_for_returns_null_for_unknown_os(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => 'myapp://home']]]);

        $this->assertNull($link->deepLinkFor('Windows'));
    }

    public function test_deep_link_for_returns_null_for_desktop_os(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => 'myapp://home']]]);

        $this->assertNull($link->deepLinkFor('Windows'));
    }

    public function test_deep_link_for_returns_ios_uri(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => 'myapp://home']]]);

        $this->assertSame('myapp://home', $link->deepLinkFor('iOS'));
    }

    public function test_deep_link_for_returns_android_uri(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['android' => 'myapp://product/42']]]);

        $this->assertSame('myapp://product/42', $link->deepLinkFor('Android'));
    }

    public function test_deep_link_for_returns_null_when_uri_is_empty_string(): void
    {
        $link = new Link(['meta' => ['deep_link' => ['ios' => '   ']]]);

        $this->assertNull($link->deepLinkFor('iOS'));
    }

    // -------------------------------------------------------------------------
    // cacheKey
    // -------------------------------------------------------------------------

    public function test_cache_key_returns_expected_format(): void
    {
        $this->assertSame('lf:link:1:abc123', Link::cacheKey(1, 'abc123'));
    }

    public function test_cache_key_is_unique_per_domain_and_alias(): void
    {
        $this->assertNotSame(
            Link::cacheKey(1, 'foo'),
            Link::cacheKey(2, 'foo')
        );

        $this->assertNotSame(
            Link::cacheKey(1, 'foo'),
            Link::cacheKey(1, 'bar')
        );
    }
}

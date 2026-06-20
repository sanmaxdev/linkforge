<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Linking\DomainResolver;
use Illuminate\Support\Facades\Cache;

class Link extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'domain_id', 'campaign_id', 'alias', 'long_url', 'params', 'title', 'tags', 'type',
        'password', 'expires_at', 'click_limit', 'clicks', 'is_active',
        'safety_status', 'safety_score', 'meta', 'qr_id', 'last_click_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'params' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_click_at' => 'datetime',
            'clicks' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Normalise a tag list (string or array) to lowercase, deduped slugs:
     * max 10 tags of 30 chars each. Returns null when empty.
     *
     * @param  string|array<int, string>|null  $raw
     * @return array<int, string>|null
     */
    public static function normalizeTags($raw): ?array
    {
        $parts = is_array($raw) ? $raw : preg_split('/[,\n;|]+/', (string) $raw);

        $tags = collect($parts)
            ->map(fn ($t) => trim(preg_replace('/[^a-z0-9\- ]/', '', strtolower((string) $t))))
            ->filter()
            ->unique()
            ->take(10)
            ->map(fn ($t) => substr($t, 0, 30))
            ->values()
            ->all();

        return $tags ?: null;
    }

    /**
     * Append this link's UTM / custom query parameters to a destination URL,
     * preserving any params already on the URL (existing keys win).
     */
    public function appendParams(string $url): string
    {
        $params = $this->params;
        if (empty($params) || ! is_array($params)) {
            return $url;
        }

        $hash = '';
        if (($pos = strpos($url, '#')) !== false) {
            $hash = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }

        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $existing);
        $merged = array_merge($params, $existing); // existing params take precedence

        return $base.'?'.http_build_query($merged).$hash;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LinkRule::class)->orderBy('sort');
    }

    public function clickEvents(): HasMany
    {
        return $this->hasMany(Click::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(StatDaily::class);
    }

    public function pixels(): BelongsToMany
    {
        return $this->belongsToMany(Pixel::class, 'link_pixel');
    }

    /** Host + alias (no scheme). The redirect/display layer adds the scheme. */
    public function shortUrl(): string
    {
        if ($this->relationLoaded('domain') && $this->domain) {
            $host = $this->domain->host;
        } else {
            $host = app(DomainResolver::class)->default()?->host
                ?: parse_url((string) config('app.url'), PHP_URL_HOST);
        }

        return rtrim((string) $host, '/').'/'.$this->alias;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOverLimit(): bool
    {
        return $this->click_limit !== null && $this->clicks >= $this->click_limit;
    }

    // Redirect hot-path cache --------------------------------------------------

    public static function cacheKey(int $domainId, string $alias): string
    {
        return "lf:link:{$domainId}:{$alias}";
    }

    public static function forgetCache(int $domainId, string $alias): void
    {
        Cache::forget(static::cacheKey($domainId, $alias));
    }
}
